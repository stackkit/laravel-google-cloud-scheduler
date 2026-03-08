<?php

namespace Tests;

use PHPUnit\Framework\Attributes\Test;
use Stackkit\LaravelGoogleCloudScheduler\OpenIdVerificator;

class CommandParsingTest extends TestCase
{
    #[Test]
    public function it_executes_a_command_sent_without_the_php_artisan_prefix()
    {
        // Arrange
        OpenIdVerificator::fake();

        // Act - Cloud Scheduler sends just the command name, no "php artisan" prefix
        $response = $this->call('POST', '/cloud-scheduler-job', content: 'env');

        // Assert
        $response->assertOk();
        $this->assertStringContainsString('The application environment is [testing]', $response->content());
    }
}
