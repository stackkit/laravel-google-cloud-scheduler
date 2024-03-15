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

        $payload = (new AccessToken())->verify(
            $token,
            [
                'audience' => config('cloud-scheduler.app_url'),
                'throwException' => true,
            ]
        );

        if (($payload['email'] ?? '') !== config('cloud-scheduler.service_account')) {
            throw new CloudSchedulerException('Invalid service account email');
        }
    }
}
