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

There are two ways to schedule commands using this package:

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

1 - Require the package using Composer

```bash
composer require stackkit/laravel-google-cloud-scheduler
```

2 - Define the `STACKKIT_CLOUD_SCHEDULER_APP_URL` environment variable. This should be the URL defined in the `URL` field of your Cloud Scheduler job.

```
STACKKIT_CLOUD_SCHEDULER_APP_URL=https://yourdomainname.com/cloud-scheduler-job
```

3 - Ensure PHP executable is in open_basedir. This is required for the package to run Artisan commands.

How to find the executable:

```php
php artisan tinker --execute="(new Symfony\\Component\\Process\\PhpExecutableFinder())->find()"
```

4 - Optional, but highly recommended: server configuration

Since Artisan commands are now invoked via an HTTP request, you might encounter issues with timeouts. Here's how to adjust them:

```nginx
server {
    # other server configuration ...

    location /cloud-scheduler-job {
        proxy_connect_timeout 600s;
        proxy_read_timeout 600s;
        fastcgi_read_timeout 600s;
    }

    # other locations and server configuration ...
}

```

5 - Optional, but highly recommended: set application `RUNNING_IN_CONSOLE`

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

To circumvent this, please add the following to `bootstrap/app.php`

<details>
<summary>Laravel 11</summary>
    
```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

+ if (($_SERVER['REQUEST_URI'] ?? '') === '/cloud-scheduler-job') {
+     $_ENV['APP_RUNNING_IN_CONSOLE'] = true;
+ }

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

```
</details>

<details>
<summary>Laravel 10</summary>

```php
<?php

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| The first thing we will do is create a new Laravel application instance
| which serves as the "glue" for all the components of Laravel, and is
| the IoC container for the system binding all of the various parts.
|
*/

$app = new Illuminate\Foundation\Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);

+ if (($_SERVER['REQUEST_URI'] ?? '') === '/cloud-scheduler-job') {
+     $_ENV['APP_RUNNING_IN_CONSOLE'] = true;
+ }
```
</details>

6 - Optional: whitelist route for maintenance mode

If you want to allow jobs to keep running if the application is down (`php artisan down`), update the following:

<details>
<summary>Laravel 11</summary>

```php
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->preventRequestsDuringMaintenance(
            except: [
                '/cloud-scheduler-job',
            ],
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();


```
</details>
<details>
<summary>Laravel 10</summary>

```php
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
</details>

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
