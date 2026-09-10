<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Class OrderPurchaseHistoryTest
 *
 * Unit tests covering order purchase history classification,
 * satisfactory vs non-satisfactory evaluation, and payment retry eligibility rules.
 */
class OrderPurchaseHistoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper to create a dummy client user.
     *
     * @param  array<string, mixed>  $attributes
     * @return \App\Models\User
     */
    protected function createClient(array $attributes = []): User
    {
        static $counter = 1;
        $id = 70000000 + ($counter++);

        return User::create(array_merge([
            'identification' => $id,
            'name' => "Cliente{$counter}",
            'last_name' => 'Prueba',
            'email' => "cliente_unit{$counter}@test.com",
            'phone' => '3001234567',
            'direction' => 'Carrera 10 # 20 - 30',
            'user_name' => "cliunit{$counter}",
            'password' => 'secret123',
            'email_verified_at' => now(),
            'is_active' => true,
        ], $attributes));
    }

    /**
     * Helper to create an order instance with default attributes.
     *
     * @param  \App\Models\User  $user
     * @param  array<string, mixed>  $attributes
     * @return \App\Models\Order
     */
    protected function createOrder(User $user, array $attributes = []): Order
    {
        return Order::create(array_merge([
            'reference' => Order::generateUniqueReference(),
            'user_identification' => $user->identification,
            'status' => Order::STATUS_PENDING_PAYMENT,
            'total_amount' => 50000.00,
            'currency' => 'COP',
            'customer_name' => $user->name . ' ' . $user->last_name,
            'customer_email' => $user->email,
            'customer_phone' => $user->phone,
            'customer_address' => $user->direction,
        ], $attributes));
    }

    /**
     * Test that an approved order is classified as satisfactory.
     *
     * @return void
     */
    public function test_order_identifies_satisfactory_status_when_approved(): void
    {
        $user = $this->createClient();
        $order = $this->createOrder($user, ['status' => Order::STATUS_APPROVED]);

        $this->assertTrue($order->isApproved());
        $this->assertTrue($order->isSatisfactory());
        $this->assertFalse($order->isNotSatisfactory());
    }

    /**
     * Test that a rejected order is classified as not satisfactory.
     *
     * @return void
     */
    public function test_order_identifies_not_satisfactory_status_when_rejected(): void
    {
        $user = $this->createClient();
        $order = $this->createOrder($user, ['status' => Order::STATUS_REJECTED]);

        $this->assertTrue($order->isRejected());
        $this->assertFalse($order->isApproved());
        $this->assertFalse($order->isSatisfactory());
        $this->assertTrue($order->isNotSatisfactory());
    }

    /**
     * Test that a pending payment order is classified as not satisfactory (awaiting completion).
     *
     * @return void
     */
    public function test_order_identifies_not_satisfactory_status_when_pending_payment(): void
    {
        $user = $this->createClient();
        $order = $this->createOrder($user, ['status' => Order::STATUS_PENDING_PAYMENT]);

        $this->assertTrue($order->isPendingPayment());
        $this->assertFalse($order->isSatisfactory());
        $this->assertTrue($order->isNotSatisfactory());
    }

    /**
     * Test that a cancelled order is classified as not satisfactory.
     *
     * @return void
     */
    public function test_order_identifies_not_satisfactory_status_when_cancelled(): void
    {
        $user = $this->createClient();
        $order = $this->createOrder($user, ['status' => Order::STATUS_CANCELLED]);

        $this->assertFalse($order->isSatisfactory());
        $this->assertTrue($order->isNotSatisfactory());
    }

    /**
     * Test that a rejected order allows payment retry.
     *
     * @return void
     */
    public function test_order_allows_payment_retry_when_rejected(): void
    {
        $user = $this->createClient();
        $order = $this->createOrder($user, ['status' => Order::STATUS_REJECTED]);

        $this->assertTrue($order->canRetryPayment());
        $this->assertTrue($order->canBePaid());
    }

    /**
     * Test that a pending payment order allows payment retry or initial payment.
     *
     * @return void
     */
    public function test_order_allows_payment_retry_when_pending_payment(): void
    {
        $user = $this->createClient();
        $order = $this->createOrder($user, ['status' => Order::STATUS_PENDING_PAYMENT]);

        $this->assertTrue($order->canRetryPayment());
        $this->assertTrue($order->canBePaid());
    }

    /**
     * Test that an approved order disallows payment retry.
     *
     * @return void
     */
    public function test_order_disallows_payment_retry_when_approved(): void
    {
        $user = $this->createClient();
        $order = $this->createOrder($user, ['status' => Order::STATUS_APPROVED]);

        $this->assertFalse($order->canRetryPayment());
        $this->assertFalse($order->canBePaid());
    }

    /**
     * Test that an in-cart order disallows payment retry (must go through checkout first).
     *
     * @return void
     */
    public function test_order_disallows_payment_retry_when_in_cart(): void
    {
        $user = $this->createClient();
        $order = $this->createOrder($user, ['status' => Order::STATUS_IN_CART]);

        $this->assertFalse($order->canRetryPayment());
        $this->assertFalse($order->canBePaid());
    }

    /**
     * Test that an order belongs to the correct user.
     *
     * @return void
     */
    public function test_order_belongs_to_client_user(): void
    {
        $user = $this->createClient();
        $order = $this->createOrder($user);

        $this->assertNotNull($order->user);
        $this->assertEquals($user->identification, $order->user->identification);
    }

    /**
     * Test status transitions from gateway status code.
     *
     * @return void
     */
    public function test_order_transitions_properly_upon_gateway_status_updates(): void
    {
        $user = $this->createClient();
        $order = $this->createOrder($user, ['status' => Order::STATUS_PENDING_PAYMENT]);

        // When gateway reports REJECTED
        $order->updateStatusFromGateway('REJECTED');
        $this->assertEquals(Order::STATUS_REJECTED, $order->fresh()->status);
        $this->assertTrue($order->fresh()->canRetryPayment());

        // When customer retries and gateway returns APPROVED
        $order->updateStatusFromGateway('APPROVED');
        $this->assertEquals(Order::STATUS_APPROVED, $order->fresh()->status);
        $this->assertTrue($order->fresh()->isSatisfactory());
        $this->assertFalse($order->fresh()->canRetryPayment());
    }
}
