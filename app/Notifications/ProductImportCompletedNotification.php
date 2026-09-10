<?php

namespace App\Notifications;

use App\Models\ProductImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProductImportCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public ProductImport $productImport)
    {
        $this->onQueue('notifications');
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Importación de productos finalizada: {$this->productImport->file_name}")
            ->greeting("Hola {$notifiable->name},")
            ->line("El proceso de importación del archivo '{$this->productImport->file_name}' ha concluido con estado: {$this->productImport->status}.")
            ->line("Resumen:")
            ->line("- Filas procesadas: {$this->productImport->processed_rows}")
            ->line("- Productos creados: {$this->productImport->created_count}")
            ->line("- Productos actualizados: {$this->productImport->updated_count}")
            ->line("- Filas con error: {$this->productImport->failed_count}");

        if ($this->productImport->hasErrors()) {
            $mail->line("Puedes revisar los errores detallados en el panel de administración.");
        }

        return $mail->action('Ver Productos', route('admin.products.index'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'import_id' => $this->productImport->id,
            'file_name' => $this->productImport->file_name,
            'status' => $this->productImport->status,
            'created_count' => $this->productImport->created_count,
            'updated_count' => $this->productImport->updated_count,
            'failed_count' => $this->productImport->failed_count,
        ];
    }
}
