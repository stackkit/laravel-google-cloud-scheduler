<?php

namespace Tests;

use Firebase\JWT\JWT;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stackkit\LaravelGoogleCloudScheduler\CloudSchedulerException;
use Stackkit\LaravelGoogleCloudScheduler\Command;
use Stackkit\LaravelGoogleCloudScheduler\OpenIdVerificator;
use Stackkit\LaravelGoogleCloudScheduler\TaskHandler;
use Throwable;

class TaskHandlerTest extends TestCase
{
    private $taskHandler;
    private $fakeCommand;
    private $openId;
    private $request;

    public function setUp(): void
    {
        parent::setUp();

        $this->fakeCommand = \Mockery::mock(Command::class)->makePartial();

        // ensure we don't call Google services to validate the token
        $this->openId = \Mockery::mock(app(OpenIdVerificator::class))->makePartial();

        $this->request = new Request();
        $this->request->headers->add(['Authorization' => 'test']);

        $this->taskHandler = new TaskHandler(
            $this->fakeCommand,
            $this->request,
            $this->openId,
            app(Kernel::class),
            app(Schedule::class),
            Container::getInstance()
        );
    }

    /** @test */
    public function it_executes_the_incoming_command()
    {
        $this->fakeCommand->shouldReceive('capture')->andReturn('env');
        $this->openId->shouldReceive('guardAgainstInvalidOpenIdToken')->andReturnNull();
        $this->openId->shouldReceive('decodeToken')->andReturnNull();

        $output = $this->taskHandler->handle();

        $this->assertEquals('Current application environment: testing', $output);
    }

    /** @test */
    public function it_requires_a_jwt()
    {
        $this->fakeCommand->shouldReceive('capture')->andReturn('env');
        $this->openId->shouldReceive('guardAgainstInvalidOpenIdToken')->andReturnNull();

        $this->request->headers->remove('Authorization');

        $this->expectException(CloudSchedulerException::class);

        $this->taskHandler->handle();
    }

    /** @test */
    public function it_requires_a_jwt_signed_by_google()
    {
        $this->fakeCommand->shouldReceive('capture')->andReturn('env');

        $dummyJwt = JWT::encode('test', 'test');

        $this->request->headers->add(['Authorization' => 'Bearer ' . $dummyJwt]);

        $this->expectException(CloudSchedulerException::class);
        $this->expectExceptionMessage('Could not decode token');

        $this->taskHandler->handle();
    }

    /** @test */
    public function the_issue_identifier_should_be_google()
    {
        $this->expectExceptionMessage('The given OpenID token is not valid');

        $this->openId->shouldReceive('decodeToken')->andReturn((object) [
            'iss' => 'accounts.not-google.com',
        ]);

        $this->taskHandler->handle();
    }

    /** @test */
    public function the_token_must_not_be_expired()
    {
        $this->expectExceptionMessage('The given OpenID token has expired');

        $this->openId->shouldReceive('decodeToken')->andReturn((object) [
            'iss' => 'accounts.google.com',
            'exp' => time() - 10,
        ]);

        $this->taskHandler->handle();
    }

    /** @test */
    public function the_aud_claim_must_be_the_same_as_the_app_id()
    {
        config()->set('laravel-google-cloud-scheduler.app_url', 'my-application.com');
        $this->fakeCommand->shouldReceive('capture')->andReturn('env');
        $this->openId->shouldReceive('decodeToken')->andReturn((object) [
            'iss' => 'accounts.google.com',
            'exp' => time() + 10,
            'aud' => 'my-application.com',
        ])->byDefault();

        try {
            $this->taskHandler->handle();
        } catch (Throwable $e) {
            $this->fail('The command should not have thrown an exception');
        }

        $this->openId->shouldReceive('decodeToken')->andReturn((object) [
            'iss' => 'accounts.google.com',
            'exp' => time() + 10,
            'aud' => 'my-other-application.com',
        ]);

        $this->expectException(CloudSchedulerException::class);
        $this->expectExceptionMessage('The given OpenID token is not valid');

        $this->taskHandler->handle();
    }
}
