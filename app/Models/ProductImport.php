<?php

namespace App\Models;

use App\Services\Product\ProductImportResult;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class ProductImport
 *
 * Tracks asynchronous spreadsheet import jobs for products.
 *
 * @property int $id
 * @property int|null $user_identification
 * @property string $file_name
 * @property string $file_path
 * @property string $status
 * @property int $total_rows
 * @property int $processed_rows
 * @property int $created_count
 * @property int $updated_count
 * @property int $failed_count
 * @property array<int, array{row: int, errors: array<string, list<string>>, data: array<string, mixed>}>|null $errors
 * @property string|null $error_message
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $user
 */
class ProductImport extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_identification',
        'file_name',
        'file_path',
        'status',
        'total_rows',
        'processed_rows',
        'created_count',
        'updated_count',
        'failed_count',
        'errors',
        'error_message',
        'started_at',
        'completed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'created_count' => 'integer',
            'updated_count' => 'integer',
            'failed_count' => 'integer',
            'errors' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * User who requested the import.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_identification', 'identification');
    }

    /**
     * Check if import is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if import is processing.
     */
    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    /**
     * Check if import has completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if import has failed.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if there are any recorded row errors.
     */
    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }

    /**
     * Mark the import as processing.
     */
    public function markAsProcessing(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'started_at' => now(),
        ]);
    }

    /**
     * Mark the import as completed using the result metrics.
     */
    public function markAsCompleted(ProductImportResult $result): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'total_rows' => $result->totalProcessed,
            'processed_rows' => $result->totalProcessed,
            'created_count' => $result->createdCount,
            'updated_count' => $result->updatedCount,
            'failed_count' => count($result->errors),
            'errors' => $result->errors,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark the import as failed with an error message.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
    }

    /**
     * User-friendly summary message of the import.
     */
    public function getSummaryMessage(): string
    {
        $parts = [];
        if ($this->created_count > 0) {
            $parts[] = "{$this->created_count} producto(s) creado(s)";
        }
        if ($this->updated_count > 0) {
            $parts[] = "{$this->updated_count} producto(s) actualizado(s)";
        }

        if (empty($parts)) {
            if ($this->hasErrors() || $this->isFailed()) {
                return 'No se importó ningún producto debido a errores en los datos del archivo.';
            }

            return 'El archivo no contiene registros de productos para importar.';
        }

        $summary = 'Importación completada con éxito: ' . implode(' y ', $parts) . '.';
        if ($this->failed_count > 0) {
            $summary .= " Sin embargo, se encontraron errores en {$this->failed_count} fila(s).";
        }

        return $summary;
    }
}
