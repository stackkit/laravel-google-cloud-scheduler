<?php

namespace Stackkit\LaravelGoogleCloudScheduler;

class Command
{
    public function capture()
    {
        return file_get_contents('php://input');
    }

    public function captureWithoutArtisan()
    {
        return trim(str_replace('php artisan', '', $this->capture()));
    }
}
