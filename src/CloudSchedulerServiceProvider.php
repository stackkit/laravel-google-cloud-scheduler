<?php

namespace Stackkit\LaravelGoogleCloudScheduler;

use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use Illuminate\Routing\Router;

class CloudSchedulerServiceProvider extends LaravelServiceProvider
{
    public function boot(Router $router)
    {
        $this->registerRoutes($router);
    }

    private function registerRoutes(Router $router)
    {
        $router->post('cloud-scheduler-job', [TaskHandler::class, 'handle']);
    }
}
