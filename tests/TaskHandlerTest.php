<?php

namespace Tests;

use Firebase\JWT\JWT;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stackkit\LaravelGoogleCloudScheduler\CloudSchedulerException;
use Stackkit\LaravelGoogleCloudScheduler\Command;
use Stackkit\LaravelGoogleCloudScheduler\OpenIdVerificator;
use Stackkit\LaravelGoogleCloudScheduler\TaskHandler;

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
            app(JWT::class)
        );
    }

    /** @test */
    public function it_executes_the_incoming_command()
    {
        $this->fakeCommand->shouldReceive('capture')->andReturn('env');
        $this->openId->shouldReceive('guardAgainstInvalidOpenIdToken')->andReturnNull();

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
        $this->expectExceptionMessage('Unauthorized');

        $this->taskHandler->handle();
    }
}
