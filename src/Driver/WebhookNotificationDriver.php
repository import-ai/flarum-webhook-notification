<?php

namespace ImportAI\WebhookNotification\Driver;

use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Notification\Driver\NotificationDriverInterface;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
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

        if (empty($webhookUrl) || count($users) === 0) {
            return;
        }

        // Filter users who have enabled webhook notifications for this type
        $type = $blueprint::getType();
        $preferenceKey = User::getNotificationPreferenceKey($type, 'webhook');

        $recipients = array_filter($users, function ($user) use ($preferenceKey) {
            if (! $user instanceof User) {
                return false;
            }
            return (bool) $user->getPreference($preferenceKey);
        });

        if (count($recipients) === 0) {
            return;
        }

        $this->queue->push(new SendWebhookNotificationJob($blueprint, $recipients));
    }

    public function registerType(string $blueprintClass, array $driversEnabledByDefault): void
    {
        // Always enable webhook by default for all notification types
        User::registerPreference(
            User::getNotificationPreferenceKey($blueprintClass::getType(), 'webhook'),
            'boolval',
            true
        );
    }
}
