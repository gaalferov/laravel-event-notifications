<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Mailtrap\MailtrapClient;
use Mailtrap\Mime\MailtrapEmail;
use Symfony\Component\Mime\Address;

/**
 * Sends a single notification via Mailtrap's template API.
 *
 * One template UUID per notification key (configured in `config/notifications.php`).
 * Each send wraps Mailtrap errors in Log::error so one bad send does not break
 * the calling listener or the batch of sends in the weekly digest.
 */
class NotificationMailer
{
    public function send(string $notificationKey, string $recipientEmail, string $recipientName, array $variables): bool
    {
        $templateUuid = config("notifications.templates.{$notificationKey}");

        if (! $templateUuid) {
            Log::warning('Mailtrap template UUID not configured - skipping notification', [
                'notification' => $notificationKey,
                'recipient' => $recipientEmail,
            ]);

            return false;
        }

        try {
            $email = (new MailtrapEmail)
                ->from(new Address(config('mail.from.address'), config('mail.from.name')))
                ->to(new Address($recipientEmail, $recipientName))
                ->templateUuid($templateUuid)
                ->templateVariables($variables);

            MailtrapClient::initSendingEmails(
                apiKey: config('services.mailtrap.api_key'),
                isSandbox: (bool) config('services.mailtrap.sandbox'),
                inboxId: config('services.mailtrap.inbox_id'),
            )->send($email);

            Log::info('Notification email sent', [
                'notification' => $notificationKey,
                'recipient' => $recipientEmail,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to send notification email', [
                'notification' => $notificationKey,
                'recipient' => $recipientEmail,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
