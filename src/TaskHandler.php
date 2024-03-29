<?php

namespace Stackkit\LaravelGoogleCloudScheduler;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Artisan;

class TaskHandler
{
    private $schedule;

    public function __construct(
        private Command $command,
        private Container $container
    ) {
        Artisan::bootstrap();

        $this->schedule = $container->make(Schedule::class);
    }

    /**
     * @throws CloudSchedulerException
     */
    public function handle()
    {
        if (config('cloud-scheduler.disable_task_handler')) {
            abort(404);
        }

        if (config('cloud-scheduler.disable_token_verification') !== true) {
            OpenIdVerificator::verify(request()->bearerToken(), []);
        }

        set_time_limit(0);

        $output = $this->runCommand($this->command->captureWithoutArtisan());

        return $this->cleanOutput($output);
    }

    private function runCommand($command)
    {
        if ($this->isScheduledCommand($command)) {
            $scheduledCommand = $this->getScheduledCommand($command);

            if ($scheduledCommand->withoutOverlapping && ! $scheduledCommand->mutex->create($scheduledCommand)) {
                return null;
            }

            $scheduledCommand->callBeforeCallbacks($this->container);

            Artisan::call($command);

            $scheduledCommand->callAfterCallbacks($this->container);
        } else {
            Artisan::call($command);
        }

        return Artisan::output();
    }

    private function isScheduledCommand($command)
    {
        return ! is_null($this->getScheduledCommand($command));
    }

    private function getScheduledCommand($command)
    {
        $events = $this->schedule->events();

        foreach ($events as $event) {
            if (! is_string($event->command)) {
                continue;
            }

            $eventCommand = $this->commandWithoutArtisan($event->command);

            if ($command === $eventCommand) {
                return $event;
            }
        }

        return null;
    }

    private function commandWithoutArtisan($command)
    {
        $parts = explode(ARTISAN_BINARY, $command);

        return substr($parts[1], 2, strlen($parts[1]));
    }

    private function cleanOutput($output)
    {
        return trim($output);
    }
}
