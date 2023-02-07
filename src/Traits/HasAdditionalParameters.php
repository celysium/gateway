<?php

namespace Celysium\Gateway\Traits;

trait HasAdditionalParameters
{
    protected array $parameters = [];

    /**
     * @param string|array $key
     * @param null $value
     * @return $this
     */
    public function parameter(string|array $key, $value = null): static
    {
        $name = is_array($key) ? $key : [$key => $value];

        foreach ($name as $key => $value) {
            $this->parameters[$key] = $value;
        }

        return $this;
    }

    /**
     * Retrieve detail using its name
     *
     * @param string $key
     * @param mixed|null $default
     * @return mixed
     */
    public function getParameter(string $key, mixed $default = null): mixed
    {
        return $this->parameters[$key] ?? $default;
    }

    /**
     * Get the value of details
     */
    public function parameters() : array
    {
        return $this->parameters;
    }
}