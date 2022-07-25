<?php

namespace Tests;

use Illuminate\Console\Application as ConsoleApplication;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Container\Container;
use Illuminate\Log\LogManager;
use Mockery;
use Stackkit\LaravelGoogleCloudScheduler\CloudSchedulerException;
use Stackkit\LaravelGoogleCloudScheduler\Command;
use Stackkit\LaravelGoogleCloudScheduler\OpenIdVerificator;
use Stackkit\LaravelGoogleCloudScheduler\TaskHandler;
use UnexpectedValueException;

class TaskHandlerTest extends TestCase
{
    private $taskHandler;
    private $fakeCommand;

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

        request()->headers->add(['Authorization' => 'Bearer test']);

        $this->taskHandler = new TaskHandler(
            $this->fakeCommand,
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
        OpenIdVerificator::fake();

        $this->fakeCommand->shouldReceive('capture')->andReturn('env');

        $output = $this->taskHandler->handle();

        if (version_compare(app()->version(), '9.0.0', '>=')) {
            $this->assertStringContainsString('The application environment is [testing]', $output);
        } else {
            $this->assertEquals('Current application environment: testing', $output);
        }
    }

    /** @test */
    public function it_requires_a_jwt()
    {
        $this->fakeCommand->shouldReceive('capture')->andReturn('env');

        request()->headers->remove('Authorization');

        $this->expectException(CloudSchedulerException::class);

        $this->taskHandler->handle();
    }

    /** @test */
    public function it_requires_a_jwt_signed_by_google()
    {
        $this->fakeCommand->shouldReceive('capture')->andReturn('env');

        $this->expectException(UnexpectedValueException::class);

        $this->taskHandler->handle();
    }

    /** @test */
    public function it_prevents_overlapping_if_the_command_is_scheduled_without_overlapping()
    {
        OpenIdVerificator::fake();

        $this->fakeCommand->shouldReceive('capture')->andReturn('test:command');

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
        OpenIdVerificator::fake();

        $this->fakeCommand->shouldReceive('capture')->andReturn('test:command2');

        $this->taskHandler->handle();

        $this->log->shouldHaveReceived()->info('log after')->once();
        $this->log->shouldHaveReceived()->warning('log before')->once();
        $this->log->shouldHaveReceived()->debug('did something testy')->once();
    }

    /** @test */
    public function it_can_run_the_schedule_run_command()
    {
        OpenIdVerificator::fake();

        $this->fakeCommand->shouldReceive('capture')->andReturn('schedule:run');

        $this->taskHandler->handle();

        $this->log->shouldHaveReceived()->info('log after')->once();
        $this->log->shouldHaveReceived()->warning('log before')->once();
        $this->log->shouldHaveReceived()->info('log call')->once();

        // @todo - can't test commands run from schedule:run because testbench has no artisan binary.
        // Log::assertLoggedMessage('debug', 'did something testy');
    }
}
