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
    public function __construct(
        protected Queue $queue,
        protected SettingsRepositoryInterface $settings,
    ) {}

    public function send(BlueprintInterface $blueprint, array $users): void
    {
        $url = $this->settings->get('import-ai-webhook-notification.webhook_url');
        if (!$url) return;

        $type = $blueprint::getType();
        $filteredUsers = array_filter($users, fn($u) =>
            $u->getPreference(User::getNotificationPreferenceKey($type, 'webhook'))
        );

        if (empty($filteredUsers)) return;

        $defaultLocale = $this->settings->get('default_locale', 'en');

        $usersWithLang = array_map(function ($user) use ($defaultLocale) {
            $userData = $user->toArray();
            $userData['locale'] = $user->getPreference('locale') ?: $defaultLocale;
            return $userData;
        }, $filteredUsers);

        $this->queue->push(new SendWebhookNotificationJob(
            url: $url,
            payload: [
                'event' => 'notification',
                'timestamp' => \Illuminate\Support\Carbon::now()->toIso8601String(),
                'type' => $type,
                'subject_model' => $blueprint::getSubjectModel(),
                'from_user' => $blueprint->getFromUser()?->toArray(),
                'subject' => $blueprint->getSubject()?->toArray(),
                'data' => $blueprint->getData(),
                'users' => $usersWithLang,
            ],
            token: $this->settings->get('import-ai-webhook-notification.webhook_token'),
            timeout: (int) $this->settings->get('import-ai-webhook-notification.timeout', 30)
        ));
    }

    public function registerType(string $blueprintClass, array $driversEnabledByDefault): void
    {
        User::registerPreference(
            User::getNotificationPreferenceKey($blueprintClass::getType(), 'webhook'),
            'boolval',
            true
        );
    }
}
