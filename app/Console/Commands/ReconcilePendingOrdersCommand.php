<?php

namespace App\Console\Commands;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Order;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReconcilePendingOrdersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:reconcile-pending
                            {--minutes=15 : Antigüedad mínima en minutos de las órdenes pendientes a consultar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcilia órdenes pendientes de pago consultando su estado en PlaceToPay';

    /**
     * The payment gateway service instance.
     *
     * @var \App\Contracts\PaymentGatewayInterface
     */
    protected PaymentGatewayInterface $gateway;

    /**
     * Create a new command instance.
     *
     * @param  \App\Contracts\PaymentGatewayInterface  $gateway
     * @return void
     */
    public function __construct(PaymentGatewayInterface $gateway)
    {
        parent::__construct();
        $this->gateway = $gateway;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');
        if ($minutes < 0) {
            $minutes = 15;
        }

        $cutoffTime = Carbon::now()->subMinutes($minutes);

        $this->info("Iniciando reconciliación de órdenes pendientes con más de {$minutes} minutos de antigüedad (anteriores a {$cutoffTime->toDateTimeString()})...");

        $processedCount = 0;
        $approvedCount = 0;
        $rejectedCount = 0;
        $pendingCount = 0;
        $errorCount = 0;

        $query = Order::query()
            ->where('status', Order::STATUS_PENDING_PAYMENT)
            ->whereNotNull('request_id')
            ->where('request_id', '!=', '')
            ->where('updated_at', '<=', $cutoffTime);

        $totalOrders = $query->count();

        if ($totalOrders === 0) {
            $this->info('No se encontraron órdenes pendientes para reconciliar.');

            return self::SUCCESS;
        }

        $this->info("Se encontraron {$totalOrders} orden(es) para verificar.");

        $query->chunkById(100, function ($orders) use (
            &$processedCount,
            &$approvedCount,
            &$rejectedCount,
            &$pendingCount,
            &$errorCount
        ) {
            foreach ($orders as $order) {
                $processedCount++;

                try {
                    $statusData = $this->gateway->getSessionStatus($order->request_id);
                    $gatewayStatus = $statusData['status']['status'] ?? null;

                    if (! $gatewayStatus) {
                        throw new Exception("Respuesta de pasarela sin campo de estado válido.");
                    }

                    $previousStatus = $order->status;
                    $order->updateStatusFromGateway($gatewayStatus, $statusData);
                    $order->refresh();

                    Log::info("Orden #{$order->reference} (RequestId: {$order->request_id}) reconciliada. Estado pasarela: {$gatewayStatus}. Estado anterior: {$previousStatus}. Estado actual: {$order->status}.");

                    if ($order->isApproved()) {
                        $approvedCount++;
                        $this->line("<fg=green>✓</> Orden #{$order->reference}: APROBADA (RequestId: {$order->request_id})");
                    } elseif ($order->isReversed()) {
                        $this->line("<fg=magenta>↺</> Orden #{$order->reference}: REVERTIDA POR STOCK (RequestId: {$order->request_id})");
                    } elseif ($order->isRefundPending()) {
                        $this->line("<fg=red>⚠</> Orden #{$order->reference}: REEMBOLSO PENDIENTE POR STOCK (RequestId: {$order->request_id})");
                    } elseif ($order->isRejected()) {
                        $rejectedCount++;
                        $this->line("<fg=yellow>✗</> Orden #{$order->reference}: RECHAZADA (RequestId: {$order->request_id})");
                    } else {
                        $pendingCount++;
                        $this->line("<fg=blue>⏳</> Orden #{$order->reference}: AÚN PENDIENTE (RequestId: {$order->request_id})");
                    }
                } catch (Throwable $e) {
                    $errorCount++;
                    Log::error("Error reconciliando orden #{$order->reference} (RequestId: {$order->request_id}): " . $e->getMessage());
                    $this->error("Error al procesar orden #{$order->reference}: " . $e->getMessage());
                }
            }
        });

        $this->newLine();
        $this->info('Reconciliación completada.');
        $this->table(
            ['Total Procesadas', 'Aprobadas', 'Rechazadas', 'Aún Pendientes', 'Errores'],
            [[$processedCount, $approvedCount, $rejectedCount, $pendingCount, $errorCount]]
        );

        return self::SUCCESS;
    }
}
