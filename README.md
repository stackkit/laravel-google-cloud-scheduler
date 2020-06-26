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

# Requirements

This package requires Laravel 5.6 or higher.

Please check the table below for supported Laravel and PHP versions:

|Laravel Version| PHP Version |
|---|---|
| 5.6 | 7.2 or 7.3
| 5.7 | 7.2 or 7.3
| 5.8 | 7.2 or 7.3 or 7.4
| 6.x | 7.2 or 7.3 or 7.4
| 7.x | 7.2 or 7.3 or 7.4

# Installation

(1) Require the package using Composer

```bash
composer require stackkit/laravel-google-cloud-scheduler
```

# Cloud Scheduler Example

Here is an example job that will run `php artisan inspire` every minute.

These are the most important settings:
- Target must be `HTTP`
- URL must be `yourdomainname.com/cloud-scheduler-job`
- Auth header must be OIDC token!

<img src="/example.png">

# Planned features

Laravel's console kernel allows to execute actions after the command has finished running (such as ping a service) and prevent overlapping. This package does not support those features at this time. The goal is to eventually read the configured settings for a command (as defined in the console kernel) and execute the command exacty as defined there  so that methods like `thenPing` and `withoutOverlapping` work out of the box without the need to configure anything.
