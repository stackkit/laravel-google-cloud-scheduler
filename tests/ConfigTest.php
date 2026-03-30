<?php

namespace Tests;

use PHPUnit\Framework\Attributes\Test;

class ConfigTest extends TestCase
{
    #[Test]
    public function it_returns_404_when_task_handler_is_disabled()
    {
        // Arrange
        config(['cloud-scheduler.disable_task_handler' => true]);

        // Act
        $response = $this->call('POST', '/cloud-scheduler-job', content: 'php artisan env');

        // Assert
        $response->assertStatus(404);
    }

    #[Test]
    public function it_skips_token_verification_when_disabled()
    {
        // Arrange - no bearer token and no fake; verification must be skipped by config
        config(['cloud-scheduler.disable_token_verification' => true]);

        // Act
        $response = $this->call('POST', '/cloud-scheduler-job', content: 'php artisan env');

        // Assert
        $response->assertOk();
        $this->assertStringContainsString('The application environment is [testing]', $response->content());
    }
}
