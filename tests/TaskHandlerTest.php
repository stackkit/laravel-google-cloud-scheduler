<?php

namespace Tests;

use Firebase\JWT\JWT;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Stackkit\LaravelGoogleCloudScheduler\Command;
use Stackkit\LaravelGoogleCloudScheduler\OpenIdVerificator;
use Stackkit\LaravelGoogleCloudScheduler\TaskHandler;

class TaskHandlerTest extends TestCase
{
    private $taskHandler;
    private $fakeCommand;

    public function setUp(): void
    {
        parent::setUp();

        $this->fakeCommand = \Mockery::mock(Command::class)->makePartial();

        $this->taskHandler = new TaskHandler(
            $this->fakeCommand,
            request(),
            app(OpenIdVerificator::class),
            app(JWT::class)
        );
    }

    /** @test */
    public function it_executes_the_incoming_command()
    {
        $this->fakeCommand->shouldReceive('capture')->andReturn('env');

        $output = $this->taskHandler->handle();

        $this->assertEquals('Current application environment: testing', $output);
    }
}
