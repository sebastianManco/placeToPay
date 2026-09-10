<?php

namespace App\Services\Product;

use App\Contracts\ProductSpreadsheetServiceInterface;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImport;
use App\Services\Cache\CacheVersionManager;
use Generator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\Response;

class ProductSpreadsheetService implements ProductSpreadsheetServiceInterface
{
    /**
     * Chunk size for batch processing rows.
     */
    protected const CHUNK_SIZE = 250;

    public function __construct(
        protected ?CacheVersionManager $versionManager = null
    ) {}

    /**
     * Export registered products to a downloadable spreadsheet response.
     */
    public function export(string $format = 'xlsx'): Response
    {
        $filename = 'productos_mercatodo_' . date('Ymd_His') . '.' . ltrim($format, '.');

        return (new FastExcel($this->productsGenerator()))->download($filename);
    }

    /**
     * Import products from an uploaded spreadsheet file or stored file path (.xlsx or .csv).
     */
    public function import(UploadedFile|string $file, ?ProductImport $import = null): ProductImportResult
    {
        $result = new ProductImportResult();
        $filePath = $file instanceof UploadedFile ? $file->getRealPath() : $file;

        try {
            $rows = (new FastExcel)->import($filePath);
        } catch (\Throwable $e) {
            Log::error('Error al leer el archivo Excel: ' . $e->getMessage());
            $result->errors[] = [
                'row' => 1,
                'errors' => ['file' => ['No se pudo leer el archivo. Asegúrate de que sea un archivo Excel (.xlsx) o CSV válido.']],
                'data' => [],
            ];
            return $result;
        }

        if ($import) {
            $import->update([
                'total_rows' => $rows->count(),
            ]);
        }

        // 1. Preload categories into memory cache to prevent N+1 queries
        /** @var Collection<int, Category> $categoriesById */
        $categoriesById = Category::all()->keyBy('id');
        /** @var Collection<string, Category> $categoriesByName */
        $categoriesByName = Category::all()->keyBy(function (Category $category) {
            return mb_strtolower(trim((string) $category->name));
        });

        // 2. Process rows in chunks to prevent memory spikes and optimize DB transactions
        $rowNumber = 1; // Row 1 corresponds to headers

        /** @var Collection<int, array<string, mixed>> $chunk */
        foreach ($rows->chunk(self::CHUNK_SIZE) as $chunk) {
            $pendingUpdates = [];
            $pendingCreates = [];

            foreach ($chunk as $rawRow) {
                $rowNumber++;

                $normalized = $this->normalizeRow($rawRow);

                // Skip completely empty rows
                if ($this->isEmptyRow($normalized)) {
                    continue;
                }

                $result->totalProcessed++;

                $parsedData = $this->extractProductData($normalized);

                // Validate the row data
                $validator = Validator::make($parsedData, [
                    'id' => 'nullable|integer',
                    'name' => 'required|string|max:255',
                    'price' => 'required|numeric|min:0',
                    'stock' => 'required|integer|min:0',
                    'description' => 'nullable|string',
                    'is_active' => 'nullable|boolean',
                ], [
                    'name.required' => 'El nombre del producto es obligatorio.',
                    'name.max' => 'El nombre no puede exceder 255 caracteres.',
                    'price.required' => 'El precio es obligatorio.',
                    'price.numeric' => 'El precio debe ser un valor numérico.',
                    'price.min' => 'El precio no puede ser negativo.',
                    'stock.required' => 'El stock es obligatorio.',
                    'stock.integer' => 'El stock debe ser un número entero.',
                    'stock.min' => 'El stock no puede ser negativo.',
                ]);

                if ($validator->fails()) {
                    $result->errors[] = [
                        'row' => $rowNumber,
                        'errors' => $validator->errors()->toArray(),
                        'data' => $rawRow,
                    ];
                    continue;
                }

                // Resolve category with in-memory caching
                $categoryId = $this->resolveCategoryIdCached($normalized, $categoriesById, $categoriesByName);

                $productAttributes = [
                    'name' => $parsedData['name'],
                    'description' => $parsedData['description'],
                    'price' => $parsedData['price'],
                    'stock' => $parsedData['stock'],
                    'is_active' => $parsedData['is_active'] ?? true,
                    'category_id' => $categoryId,
                ];

                if (! empty($parsedData['id'])) {
                    $pendingUpdates[] = [
                        'row' => $rowNumber,
                        'id' => (int) $parsedData['id'],
                        'attributes' => $productAttributes,
                        'rawRow' => $rawRow,
                    ];
                } else {
                    $pendingCreates[] = [
                        'row' => $rowNumber,
                        'attributes' => $productAttributes,
                        'rawRow' => $rawRow,
                    ];
                }
            }

            // 3. Batch insert and upsert within a single database transaction per chunk
            $this->processChunkDatabaseOperations($pendingUpdates, $pendingCreates, $result);

            // Update live progress if tracking import model is supplied
            if ($import) {
                $import->update([
                    'processed_rows' => $result->totalProcessed,
                    'created_count' => $result->createdCount,
                    'updated_count' => $result->updatedCount,
                    'failed_count' => count($result->errors),
                    'errors' => $result->errors,
                ]);
            }
        }

        // Invalidate product & category caches after bulk operations
        if ($this->versionManager) {
            $this->versionManager->bumpVersion(CacheVersionManager::TAG_PRODUCTS);
            $this->versionManager->bumpVersion(CacheVersionManager::TAG_REPORTS);
            $this->versionManager->bumpVersion(CacheVersionManager::TAG_CATEGORIES);
        }

        return $result;
    }

    /**
     * Process chunk database operations using bulk upsert and inserts wrapped in a transaction.
     *
     * @param array<int, array{row: int, id: int, attributes: array<string, mixed>, rawRow: array<string, mixed>}> $pendingUpdates
     * @param array<int, array{row: int, attributes: array<string, mixed>, rawRow: array<string, mixed>}> $pendingCreates
     * @param ProductImportResult $result
     */
    protected function processChunkDatabaseOperations(array $pendingUpdates, array $pendingCreates, ProductImportResult &$result): void
    {
        if (empty($pendingUpdates) && empty($pendingCreates)) {
            return;
        }

        // Preload existing products in this chunk with 1 single query
        $updateIds = array_unique(array_column($pendingUpdates, 'id'));
        $existingProducts = ! empty($updateIds)
            ? Product::whereIn('id', $updateIds)->get()->keyBy('id')
            : collect();

        DB::transaction(function () use ($pendingUpdates, $pendingCreates, $existingProducts, &$result) {
            $now = now();
            $upsertRows = [];

            foreach ($pendingUpdates as $item) {
                $id = $item['id'];
                if (! $existingProducts->has($id)) {
                    $result->errors[] = [
                        'row' => $item['row'],
                        'errors' => ['id' => ["El producto con ID {$id} no existe en el sistema."]],
                        'data' => $item['rawRow'],
                    ];
                    continue;
                }

                $upsertRows[] = array_merge(['id' => $id], $item['attributes'], [
                    'updated_at' => $now,
                ]);
                $result->updatedCount++;
            }

            if (! empty($upsertRows)) {
                Product::upsert(
                    $upsertRows,
                    ['id'],
                    ['name', 'description', 'price', 'stock', 'is_active', 'category_id', 'updated_at']
                );
            }

            $insertRows = [];
            foreach ($pendingCreates as $item) {
                $insertRows[] = array_merge($item['attributes'], [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $result->createdCount++;
            }

            if (! empty($insertRows)) {
                Product::insert($insertRows);
            }
        });
    }

    /**
     * Resolve category ID with in-memory caching to eliminate N+1 queries.
     *
     * @param array<string, mixed> $row
     * @param Collection<int, Category> $categoriesById
     * @param Collection<string, Category> $categoriesByName
     */
    protected function resolveCategoryIdCached(
        array $row,
        Collection &$categoriesById,
        Collection &$categoriesByName
    ): ?int {
        $categoryValue = $row['category'] ?? $row['categoria'] ?? $row['category_id'] ?? $row['id_categoria'] ?? null;

        if (empty($categoryValue)) {
            return null;
        }

        if (is_numeric($categoryValue)) {
            $catId = (int) $categoryValue;
            if ($categoriesById->has($catId)) {
                return $catId;
            }

            $category = Category::find($catId);
            if ($category) {
                $categoriesById->put($category->id, $category);
                $categoriesByName->put(mb_strtolower(trim((string) $category->name)), $category);
                return $category->id;
            }
        }

        $categoryName = trim((string) $categoryValue);
        $normalizedName = mb_strtolower($categoryName);

        if ($categoriesByName->has($normalizedName)) {
            return $categoriesByName->get($normalizedName)->id;
        }

        // Category not found in cache: create once, then cache
        $newCategory = Category::create([
            'name' => $categoryName,
            'is_active' => true,
        ]);

        $categoriesById->put($newCategory->id, $newCategory);
        $categoriesByName->put(mb_strtolower(trim((string) $newCategory->name)), $newCategory);

        return $newCategory->id;
    }

    /**
     * Generator for streaming products to spreadsheet.
     */
    protected function productsGenerator(): Generator
    {
        /** @var Product $product */
        foreach (Product::with('category')->orderBy('id')->cursor() as $product) {
            yield [
                'id' => $product->id,
                'name' => $product->name,
                'category' => $product->category?->name ?? '',
                'price' => (float) $product->price,
                'stock' => (int) $product->stock,
                'is_active' => $product->is_active ? 1 : 0,
                'description' => $product->description ?? '',
            ];
        }
    }

    /**
     * Normalize row keys and trim values.
     *
     * @param array<string, mixed> $rawRow
     * @return array<string, mixed>
     */
    protected function normalizeRow(array $rawRow): array
    {
        $normalized = [];
        foreach ($rawRow as $key => $value) {
            $cleanKey = trim(preg_replace('/[\x00-\x1F\x7F\xEF\xBB\xBF]/', '', (string) $key));
            $cleanKey = mb_strtolower($cleanKey);
            $normalized[$cleanKey] = is_string($value) ? trim($value) : $value;
        }

        return $normalized;
    }

    /**
     * Check if a row has no data.
     *
     * @param array<string, mixed> $row
     */
    protected function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Extract and parse standard product fields from normalized row.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    protected function extractProductData(array $row): array
    {
        $id = $row['id'] ?? $row['id_producto'] ?? $row['codigo'] ?? null;
        $name = $row['name'] ?? $row['nombre'] ?? $row['producto'] ?? null;
        $price = $row['price'] ?? $row['precio'] ?? null;
        $stock = $row['stock'] ?? $row['cantidad'] ?? null;
        $description = $row['description'] ?? $row['descripcion'] ?? null;
        $activeRaw = $row['is_active'] ?? $row['activo'] ?? $row['estado'] ?? null;

        $isActive = true;
        if ($activeRaw !== null && $activeRaw !== '') {
            if (is_bool($activeRaw)) {
                $isActive = $activeRaw;
            } else {
                $lower = mb_strtolower((string) $activeRaw);
                $isActive = ! in_array($lower, ['0', 'false', 'inactivo', 'inhabilitado', 'no'], true);
            }
        }

        return [
            'id' => (! empty($id) && is_numeric($id)) ? (int) $id : null,
            'name' => $name,
            'price' => is_numeric($price) ? (float) $price : $price,
            'stock' => is_numeric($stock) ? (int) $stock : $stock,
            'description' => ! empty($description) ? (string) $description : null,
            'is_active' => $isActive,
        ];
    }
}
