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

Cloud Tasks will make a HTTP call to your application. This package adds an endpoint to your application that accepts the HTTP call and its payload (an Artisan command) and executes the command.

#### withoutOverlapping, before, after, onSuccess, thenPing, etc

All these features are supported. This package scans your console kernel (`app/Console/Kernel.php`) to see if the scheduled command in Cloud Scheduler is also scheduled in the console kernel, If it is, it will respect all configured events/hooks associated with the command. (such as withoutOverlapping) 

# Requirements

This package requires Laravel 5.6 or higher.

Please check the table below for supported Laravel and PHP versions:

|Laravel Version| PHP Version |
|---|---|
| 5.6 | 7.3
| 5.7 | 7.3
| 5.8 | 7.3 or 7.4
| 6.x | 7.3 or 7.4 or 8.0
| 7.x | 7.3 or 7.4 or 8.0
| 8.x | 7.3 or 7.4 or 8.0

# Installation

(1) Require the package using Composer

```bash
composer require stackkit/laravel-google-cloud-scheduler
```

(2) Define the `STACKKIT_CLOUD_SCHEDULER_APP_URL` environment variable. This should be the URL defined in the `URL` field of your Cloud Scheduler job.

```
STACKKIT_CLOUD_SCHEDULER_APP_URL=https://yourdomainname.com/cloud-scheduler-job
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
