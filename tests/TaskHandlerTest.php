<?php

namespace Tests;

use Firebase\JWT\JWT;
use Firebase\JWT\SignatureInvalidException;
use GuzzleHttp\Client;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Console\Application as ConsoleApplication;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Log\LogManager;
use Illuminate\Support\Facades\Event;
use Mockery;
use phpseclib\Crypt\RSA;
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
    private $jwt;

    /**
     * @var LogManager
     */
    private $laravelLogger = null;

    /**
     * @var Mockery\Mock
     */
    private $log;

    public function setUp(): void
    {
        parent::setUp();

        $this->fakeCommand = Mockery::mock(Command::class)->makePartial();

        config()->set('laravel-google-cloud-scheduler.app_url', 'my-application.com');

        // We don't have a valid token to test with, so for now act as if its always valid
        $this->app->instance(JWT::class, ($this->jwt = Mockery::mock(new JWT())->byDefault()->makePartial()));
        $this->jwt->shouldReceive('decode')->andReturn((object) [
            'iss' => 'accounts.google.com',
            'aud' => 'my-application.com',
            'exp' => time() + 10
        ])->byDefault();

        // ensure we don't call Google services to validate the token
        $this->openId = Mockery::mock(new OpenIdVerificator(
            new Client(),
            new RSA(),
            $this->jwt
        ))->makePartial();


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

        $this->registerLogFake();
    }

    /**
     * Mock the Laravel logger so we can assert the commands are or aren't called.
     *
     * @return void
     */
    private function registerLogFake()
    {
        if (is_null($this->laravelLogger)) {
            $this->laravelLogger = app('log');
        }

        $this->log = Mockery::mock($this->laravelLogger);

        $this->app->singleton('log', function () {
            return $this->log;
        });
    }

    /** @test */
    public function it_executes_the_incoming_command()
    {
        $this->fakeCommand->shouldReceive('capture')->andReturn('env');
        $this->openId->shouldReceive('getKidFromOpenIdToken')->andReturnNull();
        $this->openId->shouldReceive('guardAgainstInvalidOpenIdToken')->andReturnNull();
        $this->openId->shouldReceive('decodeOpenIdToken')->andReturnNull();

        $output = $this->taskHandler->handle();

        $this->assertEquals('Current application environment: testing', $output);
    }

    /** @test */
    public function it_requires_a_jwt()
    {
        $this->fakeCommand->shouldReceive('capture')->andReturn('env');
        $this->openId->shouldReceive('getKidFromOpenIdToken')->andReturnNull();
        $this->openId->shouldReceive('guardAgainstInvalidOpenIdToken')->andReturnNull();

        $this->request->headers->remove('Authorization');

        $this->expectException(CloudSchedulerException::class);

        $this->taskHandler->handle();
    }

    /** @test */
    public function it_requires_a_jwt_signed_by_google()
    {
        $this->openId->shouldReceive('getKidFromOpenIdToken')->andReturnNull();
        $this->fakeCommand->shouldReceive('capture')->andReturn('env');

        $this->jwt->shouldReceive('decode')->andReturn((object) [
            'iss' => 'accounts.dfdfdf.com',
            'aud' => 'my-application.com',
            'exp' => time() + 10
        ]);

        $this->expectException(CloudSchedulerException::class);
        $this->expectExceptionMessage('The given OpenID token is not valid');

        $this->taskHandler->handle();
    }

    /** @test */
    public function the_issue_identifier_should_be_google()
    {
        $this->expectExceptionMessage('The given OpenID token is not valid');

        $this->openId->shouldReceive('getKidFromOpenIdToken')->andReturnNull();
        $this->openId->shouldReceive('decodeOpenIdToken')->andReturn((object) [
            'iss' => 'accounts.not-google.com',
        ]);

        $this->taskHandler->handle();
    }

    /** @test */
    public function the_token_must_not_be_expired()
    {
        $this->expectExceptionMessage('The given OpenID token has expired');

        $this->openId->shouldReceive('getKidFromOpenIdToken')->andReturnNull();
        $this->openId->shouldReceive('decodeOpenIdToken')->andReturn((object) [
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
        $this->openId->shouldReceive('getKidFromOpenIdToken')->andReturnNull();
        $this->openId->shouldReceive('decodeOpenIdToken')->andReturn((object) [
            'iss' => 'accounts.google.com',
            'exp' => time() + 10,
            'aud' => 'my-application.com',
        ])->byDefault();

        try {
            $this->taskHandler->handle();
        } catch (Throwable $e) {
            $this->fail('The command should not have thrown an exception');
        }

        $this->openId->shouldReceive('decodeOpenIdToken')->andReturn((object) [
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
        $this->openId->shouldReceive('getKidFromOpenIdToken')->andReturnNull();
        $this->openId->shouldReceive('decodeOpenIdToken')->andReturnNull();

        cache()->clear();

        $this->taskHandler->handle();

        $this->log->shouldHaveReceived('debug')->once();

        $expression = '* * * * *';
        $command = ConsoleApplication::formatCommandString('test:command');
        $mutex = 'framework'.DIRECTORY_SEPARATOR.'schedule-'.sha1($expression.$command);

        cache()->add($mutex, true, 60);
        $this->registerLogFake();

        $this->taskHandler->handle();

        $this->log->shouldNotHaveReceived('debug');

        cache()->delete($mutex);

        $this->registerLogFake();

        $this->taskHandler->handle();

        $this->log->shouldNotHaveReceived('debug');
    }

    /** @test */
    public function it_runs_the_before_and_after_callbacks()
    {
        $this->fakeCommand->shouldReceive('capture')->andReturn('test:command2');
        $this->openId->shouldReceive('guardAgainstInvalidOpenIdToken')->andReturnNull();
        $this->openId->shouldReceive('getKidFromOpenIdToken')->andReturnNull();
        $this->openId->shouldReceive('decodeOpenIdToken')->andReturnNull();

        $this->taskHandler->handle();

        $this->log->shouldHaveReceived()->info('log after')->once();
        $this->log->shouldHaveReceived()->warning('log before')->once();
        $this->log->shouldHaveReceived()->debug('did something testy')->once();
    }

    /** @test */
    public function in_case_of_signature_verification_failure_it_will_retry()
    {
        Event::fake();

        $this->openId->shouldReceive('getKidFromOpenIdToken')->andReturnNull();
        $this->jwt->shouldReceive('decode')->andThrow(SignatureInvalidException::class);

        $this->expectException(SignatureInvalidException::class);

        $this->taskHandler->handle();

        Event::assertDispatched(CacheHit::class);
        Event::assertDispatched(KeyWritten::class);
    }

    /** @test */
    public function it_can_run_the_schedule_run_command()
    {
        $this->fakeCommand->shouldReceive('capture')->andReturn('schedule:run');
        $this->openId->shouldReceive('guardAgainstInvalidOpenIdToken')->andReturnNull();
        $this->openId->shouldReceive('getKidFromOpenIdToken')->andReturnNull();
        $this->openId->shouldReceive('decodeOpenIdToken')->andReturnNull();

        $this->taskHandler->handle();

        $this->log->shouldHaveReceived()->info('log after')->once();
        $this->log->shouldHaveReceived()->warning('log before')->once();
        $this->log->shouldHaveReceived()->info('log call')->once();

        // @todo - can't test commands run from schedule:run because testbench has no artisan binary.
        // Log::assertLoggedMessage('debug', 'did something testy');
    }
}
