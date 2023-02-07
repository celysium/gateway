<?php

namespace Celysium\Gateway\Contracts;

use Celysium\Gateway\GatewayForm;
use Celysium\Gateway\Receipt;

interface PaymentInterface
{
    /**
     * Create new purchase
     *
     * @param callable $callback
     */
    public function purchase(callable $callback): PaymentInterface;

    /**
     * Pay the purchase
     *
     * @return GatewayForm
     */
    public function pay() : GatewayForm;

    /**
     * verify the payment
     *
     * @param array $request
     * @return Receipt
     */
    public function verify(array $request) : Receipt;
}
