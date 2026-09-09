<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class Report
 *
 * Represents an asynchronous business intelligence and analytics report request.
 *
 * @property int $id
 * @property int|null $user_identification
 * @property string $title
 * @property string $type
 * @property string $format
 * @property string $status
 * @property array<string, mixed>|null $parameters
 * @property string|null $file_path
 * @property string|null $excel_file_path
 * @property array<string, mixed>|null $summary_data
 * @property string|null $error_message
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $user
 */
class Report extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const FORMAT_PDF = 'pdf';
    public const FORMAT_XLSX = 'xlsx';
    public const FORMAT_BOTH = 'both';

    public const TYPE_COMPLETE = 'complete';
    public const TYPE_SALES = 'sales';
    public const TYPE_ORDERS = 'orders';
    public const TYPE_TOP_PRODUCTS = 'top_products';
    public const TYPE_PAYMENTS = 'payments';
    public const TYPE_INVENTORY_ALERTS = 'inventory_alerts';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_identification',
        'title',
        'type',
        'format',
        'status',
        'parameters',
        'file_path',
        'excel_file_path',
        'summary_data',
        'error_message',
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
            'parameters' => 'array',
            'summary_data' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * User who requested the report.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_identification', 'identification');
    }

    /**
     * Check if report generation is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if report is pending execution in the queue.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if report is currently being processed.
     */
    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    /**
     * Check if report generation failed.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if a PDF file exists for this report.
     */
    public function hasPdf(): bool
    {
        return ! empty($this->file_path);
    }

    /**
     * Check if an Excel file exists for this report.
     */
    public function hasExcel(): bool
    {
        return ! empty($this->excel_file_path);
    }

    /**
     * Mark the report as processing.
     */
    public function markAsProcessing(): void
    {
        $this->update(['status' => self::STATUS_PROCESSING]);
    }

    /**
     * Mark the report as successfully completed.
     */
    public function markAsCompleted(array $summaryData = [], ?string $filePath = null, ?string $excelFilePath = null): void
    {
        $updates = [
            'status' => self::STATUS_COMPLETED,
            'summary_data' => $summaryData,
            'completed_at' => now(),
        ];

        if ($filePath !== null) {
            $updates['file_path'] = $filePath;
        }

        if ($excelFilePath !== null) {
            $updates['excel_file_path'] = $excelFilePath;
        }

        $this->update($updates);
    }

    /**
     * Mark the report as failed with an error message.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
        ]);
    }
}
