<?php

namespace Tests\Support;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        TestCommand::class,
        TestCommand2::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        $schedule->command('test:command')->withoutOverlapping();
        $schedule->command('test:command2')->before(function () {
            logger()->info('log after');
        })->after(function () {
            logger()->warning('log before');
        });
    }
}
