<?php

namespace App\Jobs;

use App\Contracts\ProductSpreadsheetServiceInterface;
use App\Models\ProductImport;
use App\Notifications\ProductImportCompletedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImportProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 600;

    /**
     * The product import model instance.
     */
    public ProductImport $productImport;

    /**
     * Create a new job instance.
     */
    public function __construct(ProductImport $productImport)
    {
        $this->productImport = $productImport;
        $this->onQueue('imports');
    }

    /**
     * Execute the job.
     */
    public function handle(ProductSpreadsheetServiceInterface $spreadsheetService): void
    {
        try {
            $this->productImport->markAsProcessing();

            $disk = Storage::disk('local');

            if (! $disk->exists($this->productImport->file_path)) {
                $errorMessage = "El archivo a importar no existe en el almacenamiento: {$this->productImport->file_path}";
                Log::error($errorMessage);
                $this->productImport->markAsFailed($errorMessage);
                return;
            }

            $absolutePath = $disk->path($this->productImport->file_path);

            $result = $spreadsheetService->import($absolutePath, $this->productImport);

            $this->productImport->markAsCompleted($result);

            // Notify user if import was triggered by an authenticated user
            if ($this->productImport->user) {
                try {
                    $this->productImport->user->notify(new ProductImportCompletedNotification($this->productImport));
                } catch (\Throwable $notificationException) {
                    Log::warning("No se pudo enviar la notificación de importación al usuario: " . $notificationException->getMessage());
                }
            }
        } catch (\Throwable $e) {
            Log::error("Error procesando ImportProductsJob #{$this->productImport->id}: " . $e->getMessage(), [
                'exception' => $e,
            ]);

            $this->productImport->markAsFailed($e->getMessage());

            throw $e;
        }
    }
}
