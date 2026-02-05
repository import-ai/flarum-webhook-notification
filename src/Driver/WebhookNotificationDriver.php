<?php

namespace ImportAI\WebhookNotification\Driver;

use Flarum\Locale\LocaleManager;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Notification\Driver\NotificationDriverInterface;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Contracts\Queue\Queue;
use ImportAI\WebhookNotification\Job\SendWebhookNotificationJob;
use ImportAI\WebhookNotification\Service\NotificationTitleRegistry;

class WebhookNotificationDriver implements NotificationDriverInterface
{
    public function __construct(
        protected Queue $queue,
        protected SettingsRepositoryInterface $settings,
        protected LocaleManager $locales,
        protected NotificationTitleRegistry $titleRegistry
    ) {}

    public function send(BlueprintInterface $blueprint, array $users): void
    {
        $url = $this->settings->get('import-ai-webhook-notification.webhook_url');
        if (!$url) return;

        // Filter users who have webhook enabled for this notification type
        $type = $blueprint::getType();
        $filteredUsers = array_filter($users, fn($u) =>
            $u->getPreference(User::getNotificationPreferenceKey($type, 'webhook'))
        );

        if (empty($filteredUsers)) return;

        // Get the base title parameters (discussion title, etc.)
        $titleParams = $this->getTitleParams($blueprint);

        // Group users by their locale preference
        $usersByLocale = $this->groupUsersByLocale($filteredUsers);

        // Generate per-user data with localized titles
        $usersWithLang = [];
        $defaultLocale = $this->locales->getLocale();

        foreach ($usersByLocale as $locale => $localeUsers) {
            // Get the translated title for this locale
            // Pass blueprint and related info for both registered callbacks and auto-discovery
            $title = $this->titleRegistry->getTitle(
                $type,
                $titleParams,
                $locale,
                $blueprint::getSubjectModel(),
                get_class($blueprint),
                $blueprint
            );

            foreach ($localeUsers as $user) {
                $userData = $user->toArray();
                $userData['lang'] = $locale;
                $userData['title'] = $title;
                $usersWithLang[] = $userData;
            }
        }

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

    /**
     * Group users by their locale preference.
     *
     * @param User[] $users
     * @return array<string, User[]>
     */
    private function groupUsersByLocale(array $users): array
    {
        $defaultLocale = $this->settings->get('default_locale', 'en');
        $groups = [];

        foreach ($users as $user) {
            $locale = $user->getPreference('locale', $defaultLocale);
            $groups[$locale][] = $user;
        }

        return $groups;
    }

    /**
     * Extract parameters needed for the notification title translation.
     */
    private function getTitleParams(BlueprintInterface $blueprint): array
    {
        $fromUser = $blueprint->getFromUser();
        $subject = $blueprint->getSubject();

        $params = [];

        // Add from_user display name
        if ($fromUser) {
            $params['{from_user}'] = $fromUser->display_name;
        }

        // Extract title from subject (discussion title)
        if ($subject) {
            // For discussions, use the title directly
            if (method_exists($subject, 'getAttribute') && ($title = $subject->getAttribute('title'))) {
                $params['{title}'] = $title;
            } elseif (isset($subject->title)) {
                $params['{title}'] = $subject->title;
            }

            // For posts, get the discussion title
            if (method_exists($subject, 'discussion') && $subject->discussion) {
                $params['{title}'] = $subject->discussion->title;
            }
        }

        return $params;
    }

    public function registerType(string $blueprintClass, array $driversEnabledByDefault): void
    {
        // Register preference so the option appears in user settings
        User::registerPreference(
            User::getNotificationPreferenceKey($blueprintClass::getType(), 'webhook'),
            'boolval',
            true
        );
    }
}
