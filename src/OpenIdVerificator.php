<?php

namespace Stackkit\LaravelGoogleCloudScheduler;

use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use phpseclib\Crypt\RSA;
use phpseclib\Math\BigInteger;

class OpenIdVerificator
{
    private const V3_CERTS = 'GOOGLE_V3_CERTS';
    private const URL_OPENID_CONFIG = 'https://accounts.google.com/.well-known/openid-configuration';
    private const URL_TOKEN_INFO = 'https://www.googleapis.com/oauth2/v3/tokeninfo';

    private $guzzle;
    private $rsa;
    private $jwt;

    public function __construct(Client $guzzle, RSA $rsa, JWT $jwt)
    {
        $this->guzzle = $guzzle;
        $this->rsa = $rsa;
        $this->jwt = $jwt;
    }

    public function guardAgainstInvalidOpenIdToken($token)
    {
        $kid = $this->getKidFromOpenIdToken($token);
        $publicKey = $this->getPublicKey($kid);

        $decodedToken = $this->jwt->decode($token, $publicKey, ['RS256']);

        /**
         * https://developers.google.com/identity/protocols/oauth2/openid-connect#validatinganidtoken
         */

        if (!in_array($decodedToken->iss, ['https://accounts.google.com', 'accounts.google.com'])) {
            throw new CloudSchedulerException('The given OpenID token is not valid');
        }

        if ($decodedToken->exp < time()) {
            throw new CloudSchedulerException('The given OpenID token has expired');
        }
    }

    public function getPublicKey($kid = null)
    {
        $v3Certs = Cache::rememberForever(self::V3_CERTS, function () {
            return $this->getv3Certs();
        });

        $cert = $kid ? collect($v3Certs)->firstWhere('kid', '=', $kid) : $v3Certs[0];

        return $this->extractPublicKeyFromCertificate($cert);
    }

    private function getv3Certs()
    {
        $jwksUri =  $this->callApiAndReturnValue(self::URL_OPENID_CONFIG, 'jwks_uri');

        return $this->callApiAndReturnValue($jwksUri, 'keys');
    }

    private function extractPublicKeyFromCertificate($certificate)
    {
        $modulus = new BigInteger(JWT::urlsafeB64Decode($certificate['n']), 256);
        $exponent = new BigInteger(JWT::urlsafeB64Decode($certificate['e']), 256);

        $this->rsa->loadKey(compact('modulus', 'exponent'));

        return $this->rsa->getPublicKey();
    }

    public function getKidFromOpenIdToken($openIdToken)
    {
        return $this->callApiAndReturnValue(self::URL_TOKEN_INFO . '?id_token=' . $openIdToken, 'kid');
    }

    private function callApiAndReturnValue($url, $value)
    {
        $response = $this->guzzle->get($url);

        $data = json_decode($response->getBody(), true);

        return Arr::get($data, $value);
    }

    public function isCached()
    {
        return Cache::has(self::V3_CERTS);
    }
}
