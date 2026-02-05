<?php

namespace ImportAI\WebhookNotification\Job;

use Flarum\Queue\AbstractJob;
use Flarum\Settings\SettingsRepositoryInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

class SendWebhookNotificationJob extends AbstractJob
{
    private array $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function handle(
        SettingsRepositoryInterface $settings,
        LoggerInterface $logger,
        Client $client
    ): void {
        $webhookUrl = $settings->get('import-ai-webhook-notification.webhook_url');
        $webhookToken = $settings->get('import-ai-webhook-notification.webhook_token');
        $timeout = (int) $settings->get('import-ai-webhook-notification.timeout', 30);
        $retryCount = (int) $settings->get('import-ai-webhook-notification.retry_count', 3);

        if (empty($webhookUrl)) {
            return;
        }

        $headers = $this->buildHeaders($webhookToken);

        $attempt = 0;
        $lastException = null;
        $maxAttempts = $retryCount + 1;

        while ($attempt < $maxAttempts) {
            try {
                $client->post($webhookUrl, [
                    'json' => $this->payload,
                    'headers' => $headers,
                    'timeout' => $timeout,
                ]);

                $logger->info('Webhook notification sent successfully', [
                    'attempt' => $attempt + 1,
                ]);

                return;
            } catch (GuzzleException $e) {
                $lastException = $e;
                $attempt++;

                $logger->warning('Webhook notification failed', [
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt < $maxAttempts) {
                    sleep(pow(2, $attempt));
                }
            }
        }

        $logger->error('Webhook notification failed after all retries', [
            'error' => $lastException ? $lastException->getMessage() : 'unknown',
        ]);
    }

    private function buildHeaders(?string $token): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'Flarum-Webhook-Notification/1.0',
            'X-Flarum-Event' => 'notification',
        ];

        if (!empty($token)) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $headers;
    }
}
