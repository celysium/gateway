<?php

namespace Celysium\Gateway;

use Celysium\gateway\Traits\HasAdditionalParameters;

/**
 * @property string $referenceId
 */

class Receipt
{
    use HasAdditionalParameters;

    public function __construct(protected string $referenceId)
    {
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
}
