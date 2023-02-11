<?php

namespace Celysium\Gateway;

class GatewayForm
{
    protected static string $viewPath;

    /**
     * Redirection form constructor.
     *
     * @param string $action
     * @param array $inputs
     * @param string $method
     */
    public function __construct(
        protected string $action,
        protected array $inputs = [],
        protected string $method = 'POST'
    )
    {}

    /**
     * Retrieve default views path.
     *
     * @return string
     */
    public static function getDefaultViewPath() : string
    {
        return dirname(__DIR__).'/../resources/views/redirect.blade.php';
    }

    /**
     * Set views path
     *
     * @param string $path
     *
     * @return void
     */
    public static function setViewPath(string $path): void
    {
        static::$viewPath = $path;
    }

    /**
     * Retrieve views path.
     *
     * @return string
     */
    public static function getViewPath() : string
    {
        return static::$viewPath ?? static::getDefaultViewPath();
    }

    /**
     * Render form.
     *
     * @return string
     */
    public function render() : string
    {
        return view(static::getViewPath())
            ->with("action", $this->action)
            ->with("inputs", $this->inputs)
            ->with("method", $this->method)
            ->render();
    }

    /**
     *
     * @return array
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * Retrieve string format of redirection form.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->render();
    }
}
