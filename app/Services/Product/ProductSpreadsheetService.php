<?php

namespace App\Services\Product;

use App\Contracts\ProductSpreadsheetServiceInterface;
use App\Models\Category;
use App\Models\Product;
use Generator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\Response;

class ProductSpreadsheetService implements ProductSpreadsheetServiceInterface
{
    /**
     * Export registered products to a downloadable spreadsheet response.
     */
    public function export(string $format = 'xlsx'): Response
    {
        $filename = 'productos_mercatodo_' . date('Ymd_His') . '.' . ltrim($format, '.');

        return (new FastExcel($this->productsGenerator()))->download($filename);
    }

    /**
     * Import products from an uploaded spreadsheet file (.xlsx or .csv).
     */
    public function import(UploadedFile $file): ProductImportResult
    {
        $result = new ProductImportResult();
        $filePath = $file->getRealPath();

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

        $rowNumber = 1; // Row 1 corresponds to headers

        foreach ($rows as $rawRow) {
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

            // Resolve Category if provided
            $categoryId = $this->resolveCategoryId($normalized);

            $productAttributes = [
                'name' => $parsedData['name'],
                'description' => $parsedData['description'],
                'price' => $parsedData['price'],
                'stock' => $parsedData['stock'],
                'is_active' => $parsedData['is_active'] ?? true,
                'category_id' => $categoryId,
            ];

            try {
                if (! empty($parsedData['id'])) {
                    $product = Product::find($parsedData['id']);

                    if ($product) {
                        $product->update($productAttributes);
                        $result->updatedCount++;
                    } else {
                        // Specified ID does not exist
                        $result->errors[] = [
                            'row' => $rowNumber,
                            'errors' => ['id' => ["El producto con ID {$parsedData['id']} no existe en el sistema."]],
                            'data' => $rawRow,
                        ];
                    }
                } else {
                    Product::create($productAttributes);
                    $result->createdCount++;
                }
            } catch (\Throwable $e) {
                Log::error("Error guardando producto fila {$rowNumber}: " . $e->getMessage());
                $result->errors[] = [
                    'row' => $rowNumber,
                    'errors' => ['database' => ['Ocurrió un error al guardar en la base de datos: ' . $e->getMessage()]],
                    'data' => $rawRow,
                ];
            }
        }

        return $result;
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

    /**
     * Resolve category ID from row data (by name or ID).
     *
     * @param array<string, mixed> $row
     */
    protected function resolveCategoryId(array $row): ?int
    {
        $categoryValue = $row['category'] ?? $row['categoria'] ?? $row['category_id'] ?? $row['id_categoria'] ?? null;

        if (empty($categoryValue)) {
            return null;
        }

        if (is_numeric($categoryValue)) {
            $category = Category::find((int) $categoryValue);
            if ($category) {
                return $category->id;
            }
        }

        $categoryName = (string) $categoryValue;
        $category = Category::whereRaw('LOWER(name) = ?', [mb_strtolower($categoryName)])->first();

        if ($category) {
            return $category->id;
        }

        // Create new category if it doesn't exist
        $newCategory = Category::create([
            'name' => $categoryName,
            'is_active' => true,
        ]);

        return $newCategory->id;
    }
}
