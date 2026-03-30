<?php

namespace Tests;

use Google\Auth\AccessToken;
use PHPUnit\Framework\Attributes\Test;

class OpenIdVerificatorTest extends TestCase
{
    #[Test]
    public function it_rejects_a_token_from_the_wrong_service_account()
    {
        // Arrange
        config(['cloud-scheduler.service_account' => 'correct@project.iam.gserviceaccount.com']);

        $mock = $this->createMock(AccessToken::class);
        $mock->method('verify')->willReturn(['email' => 'attacker@evil.com']);
        $this->app->instance(AccessToken::class, $mock);

        // Act
        $response = $this->call(
            'POST',
            '/cloud-scheduler-job',
            server: ['HTTP_AUTHORIZATION' => 'Bearer google-signed-token'],
            content: 'php artisan env'
        );

        // Assert
        $response->assertStatus(500);
        $this->assertStringContainsString('Invalid service account email', $response->content());
    }

    #[Test]
    public function it_accepts_a_token_from_the_correct_service_account()
    {
        // Arrange
        config(['cloud-scheduler.service_account' => 'scheduler@project.iam.gserviceaccount.com']);

        $mock = $this->createMock(AccessToken::class);
        $mock->method('verify')->willReturn(['email' => 'scheduler@project.iam.gserviceaccount.com']);
        $this->app->instance(AccessToken::class, $mock);

        // Act
        $response = $this->call(
            'POST',
            '/cloud-scheduler-job',
            server: ['HTTP_AUTHORIZATION' => 'Bearer google-signed-token'],
            content: 'php artisan env'
        );

        // Assert
        $response->assertOk();
        $this->assertStringContainsString('The application environment is [testing]', $response->content());
    }
}
