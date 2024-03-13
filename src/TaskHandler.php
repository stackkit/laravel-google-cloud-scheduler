<?php

namespace Stackkit\LaravelGoogleCloudScheduler;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Artisan;

class TaskHandler
{
    private $command;

    private $schedule;

    private $container;

    public function __construct(
        Command $command,
        Schedule $schedule,
        Container $container
    ) {
        $this->command = $command;
        $this->schedule = $schedule;
        $this->container = $container;
    }

    /**
     * @throws CloudSchedulerException
     */
    public function handle()
    {
        OpenIdVerificator::verify(request()->bearerToken(), []);

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
