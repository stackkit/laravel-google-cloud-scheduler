<?php

namespace Tests;

use Orchestra\Testbench\Concerns\WithWorkbench;

class TestCase extends \Orchestra\Testbench\TestCase
{
    use WithWorkbench;

    /**
     * Get package providers.  At a minimum this is the package being tested, but also
     * would include packages upon which our package depends, e.g. Cartalyst/Sentry
     * In a normal app environment these would be added to the 'providers' array in
     * the config/app.php file.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            \Stackkit\LaravelGoogleCloudScheduler\CloudSchedulerServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        if (! defined('ARTISAN_BINARY')) {
            define('ARTISAN_BINARY', __DIR__.'/../vendor/bin/testbench');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetLog();

        cache()->clear();
    }

    public function assertLogged(string $message): void
    {
        $log = file_get_contents(storage_path('log.txt'));
        $this->assertStringContainsString($message, $log);
    }

    public function assertLoggedLines(int $lines): void
    {
        $log = file_get_contents(storage_path('log.txt'));
        $this->assertCount($lines, array_filter(explode("\n", $log)));
    }

    public function resetLog(): void
    {
        file_put_contents(storage_path('log.txt'), '');
    }
}
