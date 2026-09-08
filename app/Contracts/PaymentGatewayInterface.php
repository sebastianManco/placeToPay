<?php

namespace App\Contracts;

use App\Models\Order;

/**
 * Interface PaymentGatewayInterface
 *
 * Defines the contract for payment gateway integrations (Adapter Pattern).
 */
interface PaymentGatewayInterface
{
    /**
     * Create a payment checkout session for the given order.
     *
     * @param  \App\Models\Order  $order
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function createSession(Order $order, array $options = []): array;

    /**
     * Query and obtain the current status information of an existing payment session.
     *
     * @param  string|int  $requestId
     * @return array<string, mixed>
     */
    public function getSessionStatus(string|int $requestId): array;
}
