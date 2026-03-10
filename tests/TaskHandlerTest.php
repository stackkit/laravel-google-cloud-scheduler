<?php

namespace Tests;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
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

        $event = head(app(Schedule::class)->events());

        // Simulate a command already running by pre-creating the mutex.
        $event->mutex->create($event);

        $this->assertLoggedLines(0);

        // Should be blocked because the mutex is held.
        $this->call('POST', '/cloud-scheduler-job', content: 'php artisan test:command');

        $this->assertLoggedLines(0);

        // Release the mutex (simulating the in-flight command finishing).
        $event->mutex->forget($event);

        // Should now run and automatically release the mutex when done.
        $this->call('POST', '/cloud-scheduler-job', content: 'php artisan test:command');

        $this->assertLoggedLines(1);
        $this->assertLogged('TestCommand');

        // Should run again, proving the mutex was released automatically after the previous run.
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
    public function it_releases_the_mutex_when_the_command_throws_an_exception()
    {
        OpenIdVerificator::fake();

        // Register a command that throws during its before-callback (inside the try block).
        app(Schedule::class)
            ->command('env')
            ->withoutOverlapping()
            ->before(fn () => throw new RuntimeException('forced failure'));

        // First call: before-callback throws, but the finally block must still release the mutex.
        $first = $this->call('POST', '/cloud-scheduler-job', content: 'php artisan env');
        $first->assertStatus(500);

        // Second call: if the mutex was NOT released we would get a 200 with empty body.
        // Getting another 500 proves the finally block ran and the mutex was released.
        $second = $this->call('POST', '/cloud-scheduler-job', content: 'php artisan env');
        $second->assertStatus(500);
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
