<?php

namespace Celysium\Gateway;

use Illuminate\Support\ServiceProvider;

class GatewayServiceProvider extends ServiceProvider
{
    public function boot()
    {

        /**
         * Configurations that needs to be done by user.
         */
        $this->publishes(
            [
                dirname(__DIR__).'/config/payment.php' => config_path('payment.php'),
            ],
            'config-gateway'
        );
    }

    public function register()
    {
        //
    }
}
