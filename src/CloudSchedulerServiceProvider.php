<?php

namespace Stackkit\LaravelGoogleCloudScheduler;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;

class CloudSchedulerServiceProvider extends LaravelServiceProvider
{
    public function boot(Router $router)
    {
        $this->registerRoutes($router);
        $this->registerClient();
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/cloud-scheduler.php', 'cloud-scheduler');

        $this->publishes([
            __DIR__.'/../config/cloud-scheduler.php' => config_path('cloud-scheduler.php'),
        ], 'cloud-scheduler-config');
    }

    private function registerRoutes(Router $router)
    {
        $router->post('cloud-scheduler-job', [TaskHandler::class, 'handle']);
    }

    private function registerClient()
    {
        $this->app->bind('open-id-verificator-gcs', OpenIdVerificatorConcrete::class);
    }
}
