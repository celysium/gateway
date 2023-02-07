<?php

namespace Celysium\Gateway\Contracts;

use Celysium\Gateway\GatewayForm;
use Celysium\Gateway\Receipt;

interface RefundInterface
{
    /**
     * verify the payment
     *
     * @return Receipt
     */
    public function refund() : Receipt;
}
