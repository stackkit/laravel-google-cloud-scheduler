<?php

namespace Stackkit\LaravelGoogleCloudScheduler;

use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class TaskHandler
{
    private $command;
    private $request;
    private $openId;
    private $jwt;

    public function __construct(Command $command, Request $request, OpenIdVerificator $openId, JWT $jwt)
    {
        $this->command = $command;
        $this->request = $request;
        $this->openId = $openId;
        $this->jwt = $jwt;
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

        logger($output);

        return $this->cleanOutput($output);
    }

    /**
     * @throws CloudSchedulerException
     */
    private function authorizeRequest()
    {
        if (!$this->request->hasHeader('Authorization')) {
            return;
        }

        $openIdToken = $this->request->bearerToken();
        $kid = $this->openId->getKidFromOpenIdToken($openIdToken);
        $publicKey = $this->openId->getPublicKey($kid);

        $decodedToken = $this->jwt->decode($openIdToken, $publicKey, ['RS256']);

        $this->validateToken($decodedToken);
    }

    /**
     * https://developers.google.com/identity/protocols/oauth2/openid-connect#validatinganidtoken
     *
     * @param $openIdToken
     * @throws CloudSchedulerException
     */
    protected function validateToken($openIdToken)
    {
        if (!in_array($openIdToken->iss, ['https://accounts.google.com', 'accounts.google.com'])) {
            throw new CloudSchedulerException('The given OpenID token is not valid');
        }

        if ($openIdToken->exp < time()) {
            throw new CloudSchedulerException('The given OpenID token has expired');
        }
    }

    private function cleanOutput($output)
    {
        return trim($output);
    }
}
