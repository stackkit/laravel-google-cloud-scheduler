<p align="center">
  <img src="/logo.png" width="400">
</p>
<p align="center">
<img src="https://github.com/stackkit/laravel-google-cloud-scheduler/workflows/Run%20tests/badge.svg?branch=master" alt="Build Status">
<a href="https://packagist.org/packages/stackkit/laravel-google-cloud-scheduler"><img src="https://poser.pugx.org/stackkit/laravel-google-cloud-scheduler/v/stable.svg" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/stackkit/laravel-google-cloud-scheduler"><img src="https://poser.pugx.org/stackkit/laravel-google-cloud-scheduler/license.svg" alt="License"></a>
</p>

# Introduction

This package allows you to use Google Cloud Scheduler to schedule Laravel commands.

It only supports Artisan commands at this time due to security concerns.

# How it works

Cloud Scheduler will make HTTP calls to your application. This package adds an endpoint to your application that accepts these HTTP calls with their payload (an Artisan command) and execute them.

#### withoutOverlapping, before, after, onSuccess, thenPing, etc

All these features are supported. This package scans your console kernel (`app/Console/Kernel.php`) to see if the scheduled command in Cloud Scheduler is also scheduled in the console kernel, If it is, it will respect all configured events/hooks associated with the command. (such as withoutOverlapping) 

# Requirements

This package requires Laravel 6 or higher.

Please check the table below for supported Laravel and PHP versions:

|Laravel Version| PHP Version |
|---|---|
| 6.x | 7.4 or 8.0
| 7.x | 7.4 or 8.0
| 8.x | 7.4 or 8.0
| 9.x | 8.0 or 8.1 or 8.2
| 10.x | 8.1 or 8.2

# Installation

1) Require the package using Composer

```bash
composer require stackkit/laravel-google-cloud-scheduler
```

2) Define the `STACKKIT_CLOUD_SCHEDULER_APP_URL` environment variable. This should be the URL defined in the `URL` field of your Cloud Scheduler job.

```
STACKKIT_CLOUD_SCHEDULER_APP_URL=https://yourdomainname.com/cloud-scheduler-job
```

3) Optional: whitelist route for maintenance mode

This step is optional, but highly recommended. To allow jobs to keep running if the application is down (`php artisan down`) you must modify the `PreventRequestsDuringMaintenance` middleware:

```diff
<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance as Middleware;

class PreventRequestsDuringMaintenance extends Middleware
{
    /**
     * The URIs that should be reachable while maintenance mode is enabled.
     *
     * @var array
     */
    protected $except = [
+        '/cloud-scheduler-job',
    ];
}

```

4) Optional: set application `RUNNING_IN_CONSOLE` (highly recommended)

Some Laravel service providers only register their commands if the application is being accessed through the command line (Artisan). Because we are calling Laravel scheduler from a HTTP call, that means some commands may never register, such as the Laravel Scout command:

```php
/**
 * Bootstrap any application services.
 *
 * @return void
 */
public function boot()
{
    if ($this->app->runningInConsole()) {
        $this->commands([
            FlushCommand::class,
            ImportCommand::class,
            IndexCommand::class,
            DeleteIndexCommand::class,
        ]);

        $this->publishes([
            __DIR__.'/../config/scout.php' => $this->app['path.config'].DIRECTORY_SEPARATOR.'scout.php',
        ]);
    }
}
```

To circumvent this, please add do the following:

> For projects _**NOT**_ using Laravel Octane:
    Change `public/index.php`
```diff
/*
|--------------------------------------------------------------------------
| Check If Application Is Under Maintenance
|--------------------------------------------------------------------------
|
| If the application is maintenance / demo mode via the "down" command we
| will require this file so that any prerendered template can be shown
| instead of starting the framework, which could cause an exception.
|
*/

if (file_exists(__DIR__.'/../storage/framework/maintenance.php')) {
    require __DIR__.'/../storage/framework/maintenance.php';
}
+ 
+ /*
+ |--------------------------------------------------------------------------
+ | Manually Set Running In Console for Google Cloud Scheduler
+ |--------------------------------------------------------------------------
+ |
+ | Some service providers only register their commands if the application
+ | is running from the console. Since we are calling Cloud Scheduler
+ | from the browser we must manually trick the application into
+ | thinking that it is being run from the command line.
+ |
+ */
+ 
+ if (($_SERVER['REQUEST_URI'] ?? '') === '/cloud-scheduler-job') {
+     $_ENV['APP_RUNNING_IN_CONSOLE'] = true;
+ }
+ 
/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader for
| this application. We just need to utilize it! We'll simply require it
| into the script here so we don't need to manually load our classes.
|
*/

require __DIR__.'/../vendor/autoload.php';
```

Copy the code here:

```php
/*
|--------------------------------------------------------------------------
| Manually Set Running In Console for Google Cloud Scheduler
|--------------------------------------------------------------------------
|
| Some service providers only register their commands if the application
| is running from the console. Since we are calling Cloud Scheduler
| from the browser we must manually trick the application into
| thinking that it is being run from the command line.
|
*/

if (($_SERVER['REQUEST_URI'] ?? '') === '/cloud-scheduler-job') {
    $_ENV['APP_RUNNING_IN_CONSOLE'] = true;
}
```

> For projects _**that are**_ using Laravel Octane:
    Change `public/index.php`
```diff
/*
|--------------------------------------------------------------------------
| Check If Application Is Under Maintenance
|--------------------------------------------------------------------------
|
| If the application is maintenance / demo mode via the "down" command we
| will require this file so that any prerendered template can be shown
| instead of starting the framework, which could cause an exception.
|
*/

if (file_exists(__DIR__.'/../storage/framework/maintenance.php')) {
    require __DIR__.'/../storage/framework/maintenance.php';
}
+ 
+ /*
+ |--------------------------------------------------------------------------
+ | Manually Set Running In Console for Google Cloud Scheduler
+ |--------------------------------------------------------------------------
+ |
+ | Some service providers only register their commands if the application
+ | is running from the console. Since we are calling Cloud Scheduler
+ | from the browser we must manually trick the application into
+ | thinking that it is being run from the command line.
+ |
+ */
+ 
+ if ($_ENV['LARAVEL_OCTANE']) {
+     $_ENV['APP_RUNNING_IN_CONSOLE'] = true;
+ }
+ 
/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader for
| this application. We just need to utilize it! We'll simply require it
| into the script here so we don't need to manually load our classes.
|
*/

require __DIR__.'/../vendor/autoload.php';
```

Copy the code here:

```php
/*
|--------------------------------------------------------------------------
| Manually Set Running In Console for Google Cloud Scheduler
|--------------------------------------------------------------------------
|
| Some service providers only register their commands if the application
| is running from the console. Since we are calling Cloud Scheduler
| from the browser we must manually trick the application into
| thinking that it is being run from the command line.
|
*/

if ($_ENV['LARAVEL_OCTANE']) {
    $_ENV['APP_RUNNING_IN_CONSOLE'] = true;
}
```

# Cloud Scheduler Example

Here is an example job that will run `php artisan schedule:run` every minute.

These are the most important settings:
- Target must be `HTTP`
- URL and AUD (audience) must be `https://yourdomainname.com/cloud-scheduler-job`
- Auth header must be OIDC token!

<img src="/example.png">

# Security

The job handler requires each request to have an OpenID token. Cloud Scheduler will generate an OpenID token and send it along with the job payload to the handler.

This package verifies that the token is digitally signed by Google and that it's meant for your application. Only Google Scheduler will be able to call your handler.

More information about OpenID Connect:

https://developers.google.com/identity/protocols/oauth2/openid-connect
