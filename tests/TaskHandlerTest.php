<?php

namespace Tests;

use Illuminate\Console\Application as ConsoleApplication;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Container\Container;
use Illuminate\Log\LogManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Stackkit\LaravelGoogleCloudScheduler\CloudSchedulerException;
use Stackkit\LaravelGoogleCloudScheduler\Command;
use Stackkit\LaravelGoogleCloudScheduler\OpenIdVerificator;
use Stackkit\LaravelGoogleCloudScheduler\TaskHandler;
use Tests\Support\LogOutput;
use UnexpectedValueException;
use Workbench\App\Events\TaskOutput;

class TaskHandlerTest extends TestCase
{
    private $taskHandler;
    private $fakeCommand;

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
    }

    /** @test */
    public function it_executes_the_incoming_command()
    {
        OpenIdVerificator::fake();

        $this->fakeCommand->shouldReceive('capture')->andReturn('env');

        $output = $this->taskHandler->handle();

        $this->assertStringContainsString('The application environment is [testing]', $output);
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
        Event::fake();

        $this->fakeCommand->shouldReceive('capture')->andReturn('test:command');

        cache()->clear();

        $this->taskHandler->handle();

        $this->assertLoggedLines(1);
        $this->assertLogged('TestCommand');

        $expression = '* * * * *';
        $command = ConsoleApplication::formatCommandString('test:command');
        $mutex = 'framework'.DIRECTORY_SEPARATOR.'schedule-'.sha1($expression.$command);

        cache()->add($mutex, true, 60);

        $this->taskHandler->handle();

        $this->assertLoggedLines(1);

        cache()->delete($mutex);

        $this->taskHandler->handle();

        $this->assertLoggedLines(2);
    }

    /** @test */
    public function it_runs_the_before_and_after_callbacks()
    {
        OpenIdVerificator::fake();
        Event::fake();

        $this->fakeCommand->shouldReceive('capture')->andReturn('test:command2');

        $this->taskHandler->handle();

        $this->assertLoggedLines(3);
        $this->assertLogged('log after');
        $this->assertLogged('log before');
        $this->assertLogged('TestCommand2');
    }

    /** @test */
    public function it_can_run_the_schedule_run_command()
    {
        OpenIdVerificator::fake();
        Event::fake(TaskOutput::class);
        $this->fakeCommand->shouldReceive('capture')->andReturn('schedule:run');

        $this->taskHandler->handle();

        $this->assertLoggedLines(5);
        $this->assertLogged('TestCommand');
        $this->assertLogged('TestCommand2');
        $this->assertLogged('log call');
        $this->assertLogged('log after');
        $this->assertLogged('log before');
    }
}
