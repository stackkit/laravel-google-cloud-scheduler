<?php

namespace Stackkit\LaravelGoogleCloudScheduler;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class TaskHandler
{
    private $command;
    private $request;
    private $openId;

    public function __construct(Command $command, Request $request, OpenIdVerificator $openId)
    {
        $this->command = $command;
        $this->request = $request;
        $this->openId = $openId;
    }

    /**
     * @throws CloudSchedulerException
     */
    public function handle()
    {
        $this->authorizeRequest();

        set_time_limit(0);

        Artisan::call($this->command->captureWithoutArtisan());

        $output = Artisan::output();

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

        $decodedToken = $this->openId->decodeToken($openIdToken);

        $this->openId->guardAgainstInvalidOpenIdToken($decodedToken);
    }

    private function cleanOutput($output)
    {
        return trim($output);
    }
}
