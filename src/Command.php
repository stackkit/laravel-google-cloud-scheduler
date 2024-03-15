<?php

namespace Stackkit\LaravelGoogleCloudScheduler;

class Command
{
    public function capture()
    {
        return request()->getContent();
    }

    public function captureWithoutArtisan()
    {
        return trim(str_replace('php artisan', '', $this->capture()));
    }
}
