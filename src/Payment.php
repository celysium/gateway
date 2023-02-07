<?php

namespace Celysium\Gateway;

use Exception;
use InvalidArgumentException;
use Celysium\Gateway\Contracts\RefundInterface;
use Celysium\Gateway\Exceptions\DriverNotFoundException;
use Celysium\gateway\Traits\HasAdditionalParameters;

/**
 * @property string $id
 * @property array $config
 * @property int $amount
 * @property string $transactionId
 * @property string $driver
 */

class Payment
{
    use HasAdditionalParameters;

    protected string $id;

    /**
     * Amount
     *
     * @var int
     */
    protected int $amount = 0;

    /**
     * payment transaction id
     *
     * @var string
     */
    protected string $transactionId;

    /**
     * @var string
     */
    protected string $driver;

    /**
     * @var object
     */
    protected object $config;

    /**
     * @throws Exception
     */
    public function __construct()
    {
        $this->loadConfig();
    }

    /**
     * Retrieve given value from details
     *
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this?->$name;
    }

    /**
     * @param string $id
     * @return $this
     */
    public function setId(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Set the amount of invoice
     *
     * @param int $amount
     * @return $this
     * @throws InvalidArgumentException
     */
    public function setAmount(int $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    /**
     * set transaction id
     *
     * @param string $id
     * @return $this
     */
    public function setTransactionId(string $id): static
    {
        $this->transactionId = $id;

        return $this;
    }

    /**
     * Set the value of driver
     *
     * @param string $driver
     * @return $this
     * @throws Exception
     */
    public function setVia(string $driver): static
    {
        $this->loadConfig($driver);

        return $this;
    }

    /**
     * @throws Exception
     */
    private function loadConfig(string $driver = null): void
    {
        $driver ??= config("payment.default");

        $config = config("payment.drivers.$driver");

        if ($config == null) {
            throw new DriverNotFoundException('Driver not selected or default driver does not exist.');
        }

        $this->driver = $driver;
        $this->config = (object) $config;
    }

    /**
     * @param string $url
     * @return Payment
     */
    public function callbackUrl(string $url): static
    {
        $this->config->callbackUrl = $url;

        return $this;
    }

    /**
     * @return RefundInterface
     */
    public function gateway(): RefundInterface
    {
        $gateway = $this->config['gateway'];

        return new $gateway($this);
    }
}
