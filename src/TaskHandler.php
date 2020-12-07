<?php

namespace Stackkit\LaravelGoogleCloudScheduler;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class TaskHandler
{
    private $command;
    private $request;
    private $openId;
    private $kernel;
    private $schedule;
    private $container;

    public function __construct(
        Command $command,
        Request $request,
        OpenIdVerificator $openId,
        Kernel $kernel,
        Schedule $schedule,
        Container $container
    ) {
        $this->command = $command;
        $this->request = $request;
        $this->openId = $openId;
        $this->kernel = $kernel;
        $this->schedule = $schedule;
        $this->container = $container;
    }

    /**
     * @throws CloudSchedulerException
     */
    public function handle()
    {
        $this->authorizeRequest();

        set_time_limit(0);

        $output = $this->runCommand($this->command->captureWithoutArtisan());

        return $this->cleanOutput($output);
    }

    /**
     * @throws CloudSchedulerException
     */
    private function authorizeRequest()
    {
        if (!$this->request->hasHeader('Authorization')) {
            throw new CloudSchedulerException('Unauthorized');
        }

        $openIdToken = $this->request->bearerToken();

        $kid = $this->openId->getKidFromOpenIdToken($openIdToken);

        $decodedToken = $this->openId->decodeOpenIdToken($openIdToken, $kid);

        $this->openId->guardAgainstInvalidOpenIdToken($decodedToken);
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
        return !is_null($this->getScheduledCommand($command));
    }

    private function getScheduledCommand($command)
    {
        $events = $this->schedule->events();

        foreach ($events as $event) {
            $eventCommand = $this->commandWithoutArtisan($event->command);

            if ($command === $eventCommand) {
                return $event;
            }
        }

        return null;
    }

    private function commandWithoutArtisan($command)
    {
        $parts = explode('artisan', $command);

        return substr($parts[1], 2, strlen($parts[1]));
    }

    private function cleanOutput($output)
    {
        return trim($output);
    }
}
