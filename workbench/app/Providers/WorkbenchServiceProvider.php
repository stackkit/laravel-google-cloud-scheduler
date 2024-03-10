<?php

namespace Workbench\App\Providers;

use Illuminate\Support\ServiceProvider;
use Workbench\App\Console\Commands\TestCommand;
use Workbench\App\Console\Commands\TestCommand2;

class WorkbenchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->commands([
            TestCommand::class,
            TestCommand2::class,
        ]);
    }
}
