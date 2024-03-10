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

# How it works

Cloud Scheduler will make HTTP calls to your application. This package adds an endpoint to your application that accepts these HTTP calls with their payload (an Artisan command) and execute them.

There are two ways to use this package:

<details>
<summary>1. Schedule the `schedule:run` command</summary>

This is the easiest way to use this package. You can schedule the `schedule:run` command to run every minute.
</details>

<details>
<summary>2. Schedule commands separately</summary>

If your application does not have commands that should run every minute, you may choose to schedule them individually.

If the command uses `withoutOverlapping`, `before`, `after`, `onSuccess`, `thenPing`, etc, this package will respect those settings, as long as the command is also scheduled in the console kernel.

For example, let's say we have to generate a report every day at 3:00 AM. We can schedule the `reports:generate` command to run at 3:00 AM using Cloud Scheduler. After the report is generated, OhDear should be pinged.

Firstly, schedule the command in Cloud Tasks:

<img src="/schedule-command-example.png">

Then, schedule the command in the console kernel:

```php
public function schedule(Schedule $schedule)
{
    $schedule->command('report:generate')
        ->thenPing('https://ohdear.app/ping');
}
```

The package will pick up on the scheduled settings and ping OhDear after the command has run.
</details>

# Requirements

This package requires Laravel 10 or 11.

# Installation

(1) Require the package using Composer

```bash
composer require stackkit/laravel-google-cloud-scheduler
```

(2) Define the `STACKKIT_CLOUD_SCHEDULER_APP_URL` environment variable. This should be the URL defined in the `URL` field of your Cloud Scheduler job.

```
STACKKIT_CLOUD_SCHEDULER_APP_URL=https://yourdomainname.com/cloud-scheduler-job
```

(3) Optional: whitelist route for maintenance mode

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

(4) Optional: set application `RUNNING_IN_CONSOLE` (highly recommended)

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

To circumvent this, please add the following to `public/index.php`

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
