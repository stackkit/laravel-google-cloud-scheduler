<?php

namespace Tests;

use Firebase\JWT\JWT;
use Illuminate\Console\Application as ConsoleApplication;
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
use TiMacDonald\Log\LogFake;

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

    /** @test */
    public function it_prevents_overlapping_if_the_command_is_scheduled_without_overlapping()
    {
        $this->fakeCommand->shouldReceive('capture')->andReturn('test:command');
        $this->openId->shouldReceive('guardAgainstInvalidOpenIdToken')->andReturnNull();
        $this->openId->shouldReceive('decodeToken')->andReturnNull();

        cache()->clear();

        Log::shouldReceive('debug')->twice();

        $this->taskHandler->handle();

        $expression = '* * * * *';
        $command = ConsoleApplication::formatCommandString('test:command');
        $mutex = 'framework'.DIRECTORY_SEPARATOR.'schedule-'.sha1($expression.$command);

        cache()->add($mutex, true);

        $this->taskHandler->handle();

        cache()->delete($mutex);

        $this->taskHandler->handle();
    }

    /** @test */
    public function it_runs_the_before_and_after_callbacks()
    {
        $this->fakeCommand->shouldReceive('capture')->andReturn('test:command2');
        $this->openId->shouldReceive('guardAgainstInvalidOpenIdToken')->andReturnNull();
        $this->openId->shouldReceive('decodeToken')->andReturnNull();

        Log::swap(new LogFake());

        $this->taskHandler->handle();

        Log::assertLoggedMessage('info', 'log after');
        Log::assertLoggedMessage('warning', 'log before');
        Log::assertLoggedMessage('debug', 'did something testy');
    }
}
