<?php

namespace ImportAI\WebhookNotification\Job;

use Flarum\Queue\AbstractJob;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

class SendWebhookNotificationJob extends AbstractJob
{
    public function __construct(
        private string $url,
        private array $payload,
        private ?string $token,
        private int $timeout
    ) {}

    public function handle(Client $client, LoggerInterface $logger): void
    {
        try {
            $client->post($this->url, [
                'json' => $this->payload,
                'headers' => array_filter([
                    'Content-Type' => 'application/json',
                    'Authorization' => $this->token ? 'Bearer ' . $this->token : null,
                ]),
                'timeout' => $this->timeout,
            ]);
        } catch (\Exception $e) {
            $logger->error('Webhook failed: ' . $e->getMessage());
        }
    }
}
