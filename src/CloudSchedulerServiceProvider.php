<?php

namespace Stackkit\LaravelGoogleCloudScheduler;

use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use Illuminate\Routing\Router;

class CloudSchedulerServiceProvider extends LaravelServiceProvider
{
    public function boot(Router $router)
    {
        $this->registerRoutes($router);
        $this->registerClient();
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/laravel-google-cloud-scheduler.php', 'laravel-google-cloud-scheduler');
    }

    private function registerRoutes(Router $router)
    {
        $router->post('cloud-scheduler-job', [TaskHandler::class, 'handle']);
    }

    private function registerClient()
    {
        $this->app->bind('open-id-verificator', OpenIdVerificatorConcrete::class);
    }
}
