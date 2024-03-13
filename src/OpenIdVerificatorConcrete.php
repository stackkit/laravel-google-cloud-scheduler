<?php

namespace Stackkit\LaravelGoogleCloudScheduler;

use Google\Auth\AccessToken;
use Illuminate\Support\Facades\Facade;

class OpenIdVerificatorConcrete extends Facade
{
    public function verify(?string $token, array $config): void
    {
        if (! $token) {
            throw new CloudSchedulerException('Missing [Authorization] header');
        }

        (new AccessToken())->verify(
            $token,
            [
                'audience' => config('laravel-google-cloud-scheduler.app_url'),
                'throwException' => true,
            ]
        );
    }
}
