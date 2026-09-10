<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentApprovedStockShortageNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param  \App\Models\Order  $order
     * @param  array<int, array<string, mixed>>  $shortageDetails
     * @param  array<string, mixed>|null  $reversalResult
     */
    public function __construct(
        public Order $order,
        public array $shortageDetails = [],
        public ?array $reversalResult = null
    ) {
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
        $statusText = $this->order->isReversed()
            ? 'REVERSIÓN AUTOMÁTICA COMPLETADA'
            : 'REQUIERE REEMBOLSO O GESTIÓN MANUAL PRIORITARIA';

        $mail = (new MailMessage)
            ->error()
            ->subject("URGENTE: Pago Aprobado con Inconsistencia de Stock - Orden #{$this->order->reference}")
            ->greeting("Atención Equipo de Operaciones / Soporte,")
            ->line("Se ha detectado una orden aprobada por la pasarela de pagos (PlaceToPay) con falta de existencias de inventario.")
            ->line("Estado Actual de la Orden: {$this->order->status} ({$statusText})")
            ->line("Detalles de la Orden:")
            ->line("- Referencia: #{$this->order->reference}")
            ->line("- ID de Sesión Pasarela (requestId): {$this->order->request_id}")
            ->line("- Total Debitado: {$this->order->currency} {$this->order->total_amount}")
            ->line("- Cliente: {$this->order->customer_name} ({$this->order->customer_email})")
            ->line("- Teléfono: {$this->order->customer_phone}")
            ->line("Productos con Inconsistencia:");

        foreach ($this->shortageDetails as $detail) {
            $name = $detail['name'] ?? 'Producto';
            $req = $detail['required'] ?? 0;
            $avail = $detail['available'] ?? 0;
            $mail->line("- {$name}: Solicitado: {$req}, Disponible en Stock: {$avail}");
        }

        if ($this->reversalResult) {
            $revStatus = $this->reversalResult['status']['status'] ?? 'DESCONOCIDO';
            $revMessage = $this->reversalResult['status']['message'] ?? '';
            $mail->line("Resultado de Reversión Automática PlaceToPay: {$revStatus} - {$revMessage}");
        } else {
            $mail->line("No se pudo ejecutar reversión automática inmediata (medio de pago no reversible por API o error). Se requiere gestionar reembolso manual.");
        }

        return $mail->action('Gestionar Orden en Panel de Administración', route('admin.orders.show', $this->order->id));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_reference' => $this->order->reference,
            'request_id' => $this->order->request_id,
            'status' => $this->order->status,
            'total_amount' => $this->order->total_amount,
            'shortage_details' => $this->shortageDetails,
            'reversal_result' => $this->reversalResult,
        ];
    }
}
