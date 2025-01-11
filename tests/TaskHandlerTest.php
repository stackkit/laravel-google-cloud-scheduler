<?php

namespace Tests;

use Illuminate\Console\Application as ConsoleApplication;
use Illuminate\Console\Scheduling\Event as SchedulingEvent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schedule;
use PHPUnit\Framework\Attributes\Test;
use Stackkit\LaravelGoogleCloudScheduler\OpenIdVerificator;

class TaskHandlerTest extends TestCase
{
    #[Test]
    public function it_executes_the_incoming_command()
    {
        // Arrange
        OpenIdVerificator::fake();

        // Act
        $output = $this->call('POST', '/cloud-scheduler-job', content: 'php artisan env')->content();

        // Assert
        $this->assertStringContainsString('The application environment is [testing]', $output);
    }

    #[Test]
    public function it_requires_a_jwt()
    {
        // Act
        $response = $this->call('POST', '/cloud-scheduler-job', content: 'php artisan env');

        // Assert
        $this->assertStringContainsString('Missing [Authorization] header', $response->content());
        $response->assertStatus(500);

    }

    #[Test]
    public function it_requires_a_jwt_signed_by_google()
    {
        // Act
        $response = $this
            ->withToken('hey')
            ->call('POST', '/cloud-scheduler-job', server: ['HTTP_AUTHORIZATION' => 'Bearer 123'], content: 'php artisan env');

        // Assert
        $this->assertStringContainsString('Wrong number of segments', $response->content());
        $response->assertStatus(500);
    }

    #[Test]
    public function it_prevents_overlapping_if_the_command_is_scheduled_without_overlapping()
    {
        OpenIdVerificator::fake();
        Event::fake();

        cache()->clear();

        $this->assertLoggedLines(0);

        $this->call('POST', '/cloud-scheduler-job', content: 'php artisan test:command');

        $this->assertLoggedLines(1);
        $this->assertLogged('TestCommand');

        $mutex = head(Schedule::events())->mutexName();

        cache()->add($mutex, true, 60);

        $this->call('POST', '/cloud-scheduler-job', content: 'php artisan test:command');

        $this->assertLoggedLines(1);

        cache()->delete($mutex);

        $this->call('POST', '/cloud-scheduler-job', content: 'php artisan test:command');

        $this->assertLoggedLines(2);
    }

    #[Test]
    public function it_runs_the_before_and_after_callbacks()
    {
        OpenIdVerificator::fake();

        $this->call('POST', '/cloud-scheduler-job', content: 'php artisan test:command2');

        $this->assertLoggedLines(3);
        $this->assertLogged('log after');
        $this->assertLogged('log before');
        $this->assertLogged('TestCommand2');
    }

    #[Test]
    public function it_can_run_the_schedule_run_command()
    {
        OpenIdVerificator::fake();

        $this->call('POST', '/cloud-scheduler-job', content: 'php artisan schedule:run');

        $this->assertLoggedLines(5);
        $this->assertLogged('TestCommand');
        $this->assertLogged('TestCommand2');
        $this->assertLogged('log call');
        $this->assertLogged('log after');
        $this->assertLogged('log before');
    }
}
