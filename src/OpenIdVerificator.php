<?php

namespace Stackkit\LaravelGoogleCloudScheduler;

use Illuminate\Support\Facades\Facade;

class OpenIdVerificator extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'open-id-verificator';
    }

    public static function fake(): void
    {
        self::swap(new OpenIdVerificatorFake());
    }
}
