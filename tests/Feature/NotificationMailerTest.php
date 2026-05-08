<?php

namespace Tests\Feature;

use App\Services\NotificationMailer;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class NotificationMailerTest extends TestCase
{
    public function test_send_returns_false_and_logs_when_template_uuid_missing(): void
    {
        Log::spy();
        config(['notifications.templates.teammate_invited' => null]);

        $result = app(NotificationMailer::class)->send(
            notificationKey: 'teammate_invited',
            recipientEmail: 'bob@example.com',
            recipientName: 'Bob',
            variables: ['foo' => 'bar'],
        );

        $this->assertFalse($result);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($msg) => str_contains($msg, 'template UUID not configured'))
            ->once();
    }

    public function test_send_returns_false_on_mailtrap_failure(): void
    {
        Log::spy();

        config([
            'notifications.templates.teammate_invited' => 'tmpl-fake',
            'services.mailtrap.api_key' => 'invalid_key',
            'mail.from.address' => 'no-reply@example.com',
            'mail.from.name' => 'Test',
        ]);

        $result = app(NotificationMailer::class)->send(
            notificationKey: 'teammate_invited',
            recipientEmail: 'bob@example.com',
            recipientName: 'Bob',
            variables: ['foo' => 'bar'],
        );

        $this->assertFalse($result);

        Log::shouldHaveReceived('error')
            ->withArgs(fn ($msg) => str_contains($msg, 'Failed to send notification email'))
            ->once();
    }
}
