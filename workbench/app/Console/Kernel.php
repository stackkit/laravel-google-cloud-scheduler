<?php

namespace Workbench\App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Workbench\App\Console\Commands\TestCommand;
use Workbench\App\Console\Commands\TestCommand2;

class Kernel extends \Illuminate\Foundation\Console\Kernel
{
    protected $commands = [
        TestCommand::class,
        TestCommand2::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        $schedule->command(TestCommand::class)->withoutOverlapping()->everyMinute();
        $schedule->command('test:command2')->before(function () {
            file_put_contents(storage_path('log.txt'), 'log after'.PHP_EOL, FILE_APPEND);
        })->after(function () {
            file_put_contents(storage_path('log.txt'), 'log before'.PHP_EOL, FILE_APPEND);
        });
        $schedule->call(function () {
            file_put_contents(storage_path('log.txt'), 'log call'.PHP_EOL, FILE_APPEND);
        });
    }
}
