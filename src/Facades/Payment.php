<?php
namespace Celysium\Gateway\Facades;

use Illuminate\Support\Facades\Facade;
use Celysium\Gateway\Contracts\RefundInterface;

/**
 * @method static \Celysium\Gateway\Payment id(string $id)
 * @method static \Celysium\Gateway\Payment amount(int $amount)
 * @method static \Celysium\Gateway\Payment transactionId(string $id)
 * @method static \Celysium\Gateway\Payment via(string $driver)
 * @method static \Celysium\Gateway\Payment callbackUrl(string $url)
 * @method RefundInterface gateway()
 */
class Payment extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'payment';
    }
}
