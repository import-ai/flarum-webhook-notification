<?php

namespace ImportAI\WebhookNotification\Job;

use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Queue\AbstractJob;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

class SendWebhookNotificationJob extends AbstractJob
{
    private string $blueprintClass;
    private string $blueprintType;
    private ?int $fromUserId;
    private ?string $fromUserName;
    private ?int $subjectId;
    private ?string $subjectType;
    private mixed $data;
    private array $userIds;

    public function __construct(BlueprintInterface $blueprint, array $users)
    {
        $this->blueprintClass = get_class($blueprint);
        $this->blueprintType = $blueprint::getType();

        $fromUser = $blueprint->getFromUser();
        $this->fromUserId = $fromUser ? $fromUser->id : null;
        $this->fromUserName = $fromUser ? $fromUser->display_name : null;

        $subject = $blueprint->getSubject();
        $this->subjectId = $subject ? $subject->id : null;
        $this->subjectType = $subject ? get_class($subject) : null;

        $this->data = $blueprint->getData();
        $this->userIds = array_map(function (User $user) {
            return $user->id;
        }, $users);
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

        $recipients = User::whereIn('id', $this->userIds)->get();

        if ($recipients->isEmpty()) {
            error_log('No recipients found, skipping webhook');
            return;
        }

        $payload = $this->buildPayload($recipients);
        $headers = $this->buildHeaders($webhookToken);

        $attempt = 0;
        $lastException = null;
        $maxAttempts = $retryCount + 1; // retry_count is number of retries after initial attempt

        while ($attempt < $maxAttempts) {
            try {
                $client->post($webhookUrl, [
                    'json' => $payload,
                    'headers' => $headers,
                    'timeout' => $timeout,
                ]);

                $logger->info('Webhook notification sent successfully', [
                    'type' => $this->blueprintType,
                    'recipients' => count($recipients),
                    'attempt' => $attempt + 1,
                ]);

                return;
            } catch (GuzzleException $e) {
                $lastException = $e;
                $attempt++;

                $logger->warning('Webhook notification failed', [
                    'type' => $this->blueprintType,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt < $maxAttempts) {
                    sleep(pow(2, $attempt));
                }
            }
        }

        $logger->error('Webhook notification failed after all retries', [
            'type' => $this->blueprintType,
            'error' => $lastException ? $lastException->getMessage() : 'unknown',
        ]);
    }

    private function buildPayload($recipients): array
    {
        return [
            'event' => 'notification',
            'type' => $this->blueprintType,
            'blueprint_class' => $this->blueprintClass,
            'timestamp' => \Illuminate\Support\Carbon::now()->toIso8601String(),
            'from_user' => $this->fromUserId ? [
                'id' => $this->fromUserId,
                'display_name' => $this->fromUserName,
            ] : null,
            'subject' => $this->subjectId ? [
                'id' => $this->subjectId,
                'type' => $this->subjectType,
            ] : null,
            'data' => $this->data,
            'recipients' => $recipients->map(function (User $user) {
                return [
                    'id' => $user->id,
                    'username' => $user->username,
                    'display_name' => $user->display_name,
                    'email' => $user->email,
                ];
            })->toArray(),
        ];
    }

    private function buildHeaders(?string $token): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'Flarum-Webhook-Notification/1.0',
            'X-Flarum-Event' => 'notification',
            'X-Flarum-Notification-Type' => $this->blueprintType,
        ];

        if (!empty($token)) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $headers;
    }
}
