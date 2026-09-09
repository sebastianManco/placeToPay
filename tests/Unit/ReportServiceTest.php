<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Report;
use App\Models\User;
use App\Services\Report\ReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ReportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ReportService();
        Storage::fake('local');
    }

    protected function createProduct(array $attributes = []): Product
    {
        static $p = 1;
        return Product::create(array_merge([
            'name' => 'Producto Test ' . ($p++),
            'description' => 'Descripcion test',
            'price' => 10000.00,
            'stock' => 20,
            'is_active' => true,
        ], $attributes));
    }

    protected function createOrder(array $attributes = []): Order
    {
        return Order::create(array_merge([
            'reference' => Order::generateUniqueReference(),
            'status' => Order::STATUS_APPROVED,
            'total_amount' => 10000.00,
            'currency' => 'COP',
            'customer_name' => 'Cliente Test',
            'customer_email' => 'cliente@test.com',
        ], $attributes));
    }

    public function test_get_sales_report_calculates_totals_and_average_ticket(): void
    {
        $this->createOrder(['status' => Order::STATUS_APPROVED, 'total_amount' => 10000.00]);
        $this->createOrder(['status' => Order::STATUS_APPROVED, 'total_amount' => 20000.00]);
        $this->createOrder(['status' => Order::STATUS_REJECTED, 'total_amount' => 15000.00]);
        $this->createOrder(['status' => Order::STATUS_IN_CART, 'total_amount' => 5000.00]);

        $metrics = $this->service->getSalesReport();

        $this->assertEquals(30000.00, $metrics['total_sales']);
        $this->assertEquals(2, $metrics['approved_orders_count']);
        $this->assertEquals(4, $metrics['total_orders_count']);
        $this->assertEquals(15000.00, $metrics['average_ticket']);
        $this->assertEquals(50.00, $metrics['conversion_rate']);
    }

    public function test_get_sales_report_filters_by_date_range(): void
    {
        $order1 = $this->createOrder(['status' => Order::STATUS_APPROVED, 'total_amount' => 10000.00]);
        $order1->created_at = Carbon::parse('2026-09-01 10:00:00');
        $order1->save();

        $order2 = $this->createOrder(['status' => Order::STATUS_APPROVED, 'total_amount' => 20000.00]);
        $order2->created_at = Carbon::parse('2026-09-05 10:00:00');
        $order2->save();

        $order3 = $this->createOrder(['status' => Order::STATUS_APPROVED, 'total_amount' => 30000.00]);
        $order3->created_at = Carbon::parse('2026-08-15 10:00:00');
        $order3->save();

        $metrics = $this->service->getSalesReport([
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-06',
        ]);

        $this->assertEquals(30000.00, $metrics['total_sales']);
        $this->assertEquals(2, $metrics['approved_orders_count']);
    }

    public function test_get_top_selling_products_ranks_by_quantity_in_approved_orders(): void
    {
        $prodA = $this->createProduct(['name' => 'Arroz Diana', 'price' => 5000.00]);
        $prodB = $this->createProduct(['name' => 'Aceite Premier', 'price' => 12000.00]);
        $prodC = $this->createProduct(['name' => 'Cafe Sello Rojo', 'price' => 8000.00]);

        // Approved order with Prod A and Prod B
        $orderApproved = $this->createOrder(['status' => Order::STATUS_APPROVED]);
        OrderItem::create([
            'order_id' => $orderApproved->id,
            'product_id' => $prodA->id,
            'product_name' => $prodA->name,
            'unit_price' => 5000.00,
            'quantity' => 10,
            'subtotal' => 50000.00,
        ]);
        OrderItem::create([
            'order_id' => $orderApproved->id,
            'product_id' => $prodB->id,
            'product_name' => $prodB->name,
            'unit_price' => 12000.00,
            'quantity' => 4,
            'subtotal' => 48000.00,
        ]);

        // Rejected order with Prod C (must NOT count as sales)
        $orderRejected = $this->createOrder(['status' => Order::STATUS_REJECTED]);
        OrderItem::create([
            'order_id' => $orderRejected->id,
            'product_id' => $prodC->id,
            'product_name' => $prodC->name,
            'unit_price' => 8000.00,
            'quantity' => 20,
            'subtotal' => 160000.00,
        ]);

        $top = $this->service->getTopSellingProducts();

        $this->assertCount(2, $top);
        $this->assertEquals($prodA->id, $top->first()->product_id);
        $this->assertEquals(10, $top->first()->total_quantity);
        $this->assertEquals(50000.00, $top->first()->total_revenue);

        $this->assertEquals($prodB->id, $top->last()->product_id);
        $this->assertEquals(4, $top->last()->total_quantity);
    }

    public function test_get_payment_statuses_report_groups_placetopay_statuses(): void
    {
        $this->createOrder(['status' => Order::STATUS_APPROVED, 'total_amount' => 100000.00]);
        $this->createOrder(['status' => Order::STATUS_PENDING_PAYMENT, 'total_amount' => 50000.00]);
        $this->createOrder(['status' => Order::STATUS_REJECTED, 'total_amount' => 30000.00]);
        $this->createOrder(['status' => Order::STATUS_CANCELLED, 'total_amount' => 20000.00]);

        $report = $this->service->getPaymentStatusesReport();

        $this->assertEquals(4, $report['total_transactions']);
        $this->assertEquals(200000.00, $report['total_amount']);
        $this->assertEquals(1, $report['statuses'][Order::STATUS_APPROVED]['count']);
        $this->assertEquals(100000.00, $report['statuses'][Order::STATUS_APPROVED]['amount']);
        $this->assertEquals(1, $report['statuses'][Order::STATUS_REJECTED]['count']);
        $this->assertEquals(1, $report['statuses'][Order::STATUS_PENDING_PAYMENT]['count']);
        $this->assertEquals(1, $report['statuses'][Order::STATUS_CANCELLED]['count']);
    }

    public function test_get_inventory_alerts_detects_out_of_stock_and_low_stock(): void
    {
        $out = $this->createProduct(['name' => 'Agotado', 'stock' => 0]);
        $low = $this->createProduct(['name' => 'Bajo Stock', 'stock' => 3]);
        $ok = $this->createProduct(['name' => 'Buen Stock', 'stock' => 50]);

        $alerts = $this->service->getInventoryAlerts(lowStockThreshold: 5);

        $this->assertTrue($alerts['out_of_stock']->contains('id', $out->id));
        $this->assertFalse($alerts['out_of_stock']->contains('id', $low->id));

        $this->assertTrue($alerts['low_stock']->contains('id', $low->id));
        $this->assertFalse($alerts['low_stock']->contains('id', $ok->id));
    }

    public function test_get_inventory_alerts_detects_dead_stock_with_no_sales(): void
    {
        $activeProd = $this->createProduct(['name' => 'Producto Movido', 'stock' => 40]);
        $deadProd = $this->createProduct(['name' => 'Producto Muerto', 'stock' => 60]);

        // Create sale for activeProd in approved order
        $order = $this->createOrder(['status' => Order::STATUS_APPROVED]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $activeProd->id,
            'product_name' => $activeProd->name,
            'unit_price' => $activeProd->price,
            'quantity' => 5,
            'subtotal' => 20000.00,
        ]);

        $alerts = $this->service->getInventoryAlerts(lowStockThreshold: 5, daysInactive: 30);

        // deadProd has stock > 0 but 0 sales
        $this->assertTrue($alerts['dead_stock']->contains('id', $deadProd->id));
        $this->assertFalse($alerts['dead_stock']->contains('id', $activeProd->id));
    }

    public function test_generate_pdf_and_excel_exports(): void
    {
        $this->createOrder(['status' => Order::STATUS_APPROVED, 'total_amount' => 50000.00]);
        $prod = $this->createProduct(['name' => 'Producto Test']);

        $report = Report::create([
            'title' => 'Reporte General',
            'type' => Report::TYPE_COMPLETE,
            'format' => Report::FORMAT_BOTH,
            'status' => Report::STATUS_PENDING,
        ]);

        $data = $this->service->compileReportData($report);

        $pdfPath = $this->service->generatePdf($report, $data);
        $excelPath = $this->service->generateExcel($report, $data);

        $this->assertNotEmpty($pdfPath);
        $this->assertNotEmpty($excelPath);
        Storage::disk('local')->assertExists($pdfPath);
        Storage::disk('local')->assertExists($excelPath);
    }
}
