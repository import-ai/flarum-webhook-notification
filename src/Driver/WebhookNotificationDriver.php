<?php

namespace ImportAI\WebhookNotification\Driver;

use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Notification\Driver\NotificationDriverInterface;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Queue\Queue;
use ImportAI\WebhookNotification\Job\SendWebhookNotificationJob;

class WebhookNotificationDriver implements NotificationDriverInterface
{
    protected Queue $queue;
    protected SettingsRepositoryInterface $settings;

    public function __construct(Queue $queue, SettingsRepositoryInterface $settings)
    {
        $this->queue = $queue;
        $this->settings = $settings;
    }

    public function send(BlueprintInterface $blueprint, array $users): void
    {
        $webhookUrl = $this->settings->get('import-ai-webhook-notification.webhook_url');

        // Passthrough: send to webhook regardless of users or preferences
        if (empty($webhookUrl)) {
            return;
        }

        // Passthrough raw blueprint data with toArray() conversion
        $this->queue->push(new SendWebhookNotificationJob([
            'event' => 'notification',
            'blueprint_class' => get_class($blueprint),
            'timestamp' => \Illuminate\Support\Carbon::now()->toIso8601String(),
            'type' => $blueprint::getType(),
            'subject_model' => $blueprint::getSubjectModel(),
            'from_user' => $blueprint->getFromUser()?->toArray(),
            'subject' => $blueprint->getSubject()?->toArray(),
            'data' => $blueprint->getData(),
            'users' => array_map(fn($user) => $user->toArray(), $users),
        ]));
    }

    public function registerType(string $blueprintClass, array $driversEnabledByDefault): void
    {
        // No user preferences needed for passthrough mode
    }
}
