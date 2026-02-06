<?php

namespace ImportAI\WebhookNotification\Driver;

use Flarum\Locale\LocaleManager;
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
        protected LocaleManager $locales
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

        // Group users by locale and generate per-user titles
        $defaultLocale = $this->locales->getLocale();
        $usersByLocale = [];

        foreach ($filteredUsers as $user) {
            $locale = $user->getPreference('locale', $defaultLocale);
            $usersByLocale[$locale][] = $user;
        }

        $usersWithLang = [];
        foreach ($usersByLocale as $locale => $localeUsers) {
            $title = $this->getTitle($type, $blueprint, $locale);

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
     * Get translated title for a notification type using auto-discovery.
     */
    private function getTitle(string $type, BlueprintInterface $blueprint, string $locale): string
    {
        $translator = $this->locales->getTranslator();
        $fromUser = $blueprint->getFromUser();

        // Build translation parameters
        $params = [];
        if ($fromUser) {
            $params['{username}'] = $fromUser->display_name;
            $params['user'] = $fromUser;
        }

        // Auto-discover translation key
        $key = $this->discoverTranslationKey($type, $blueprint, $locale);

        if ($key) {
            $title = $translator->trans($key, $params, null, $locale);
            if ($title !== $key) {
                return $title;
            }
        }

        // Fallback to generic
        return $translator->trans('core.forum.settings.notification_checkbox_a11y_label_template', [
            '{description}' => $type,
            '{method}' => 'webhook',
        ], null, $locale);
    }

    /**
     * Auto-discover the translation key for a notification type.
     */
    private function discoverTranslationKey(string $type, BlueprintInterface $blueprint, string $locale): ?string
    {
        $snakeType = $this->toSnakeCase($type);
        $prefixes = $this->getExtensionPrefixes($blueprint);

        // Try notification text patterns first (preferred)
        foreach ($prefixes as $ext) {
            $key = "{$ext}.forum.notifications.{$snakeType}_text";
            if ($this->translationExists($key, $locale)) {
                return $key;
            }
        }

        // Fallback to settings label patterns
        foreach ($prefixes as $ext) {
            $key = "{$ext}.forum.settings.notify_{$snakeType}_label";
            if ($this->translationExists($key, $locale)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Get possible extension prefixes from blueprint and subject.
     *
     * @return string[]
     */
    private function getExtensionPrefixes(BlueprintInterface $blueprint): array
    {
        $prefixes = [];

        // From subject model namespace
        $subjectModel = $blueprint::getSubjectModel();
        if ($subjectModel) {
            $prefix = $this->deriveExtensionFromClass($subjectModel);
            if ($prefix) {
                $prefixes[] = $prefix;
            }
        }

        // From blueprint class namespace
        $prefix = $this->deriveExtensionFromClass(get_class($blueprint));
        if ($prefix && !in_array($prefix, $prefixes)) {
            $prefixes[] = $prefix;
        }

        // From type prefix if it contains a dot (e.g., "my-extension.myType")
        if (str_contains($blueprint::getType(), '.')) {
            $parts = explode('.', $blueprint::getType());
            if (!in_array($parts[0], $prefixes)) {
                $prefixes[] = $parts[0];
            }
        }

        return $prefixes;
    }

    /**
     * Derive extension prefix from a class namespace.
     *
     * Examples:
     * - "Flarum\Discussion\Discussion" -> "core"
     * - "Flarum\Subscriptions\..." -> "flarum-subscriptions"
     * - "Vendor\ExtensionName\..." -> "vendor-extension-name"
     */
    private function deriveExtensionFromClass(string $className): ?string
    {
        if (!str_contains($className, '\\')) {
            return null;
        }

        $parts = explode('\\', $className);

        // Flarum core classes
        if ($parts[0] === 'Flarum') {
            if (count($parts) >= 2) {
                // Core models: Discussion, Post, User, Group
                if (in_array($parts[1], ['Discussion', 'Post', 'User', 'Group'])) {
                    return 'core';
                }
                // Bundled extensions: Subscriptions, Mentions, etc.
                return 'flarum-' . $this->toKebabCase($parts[1]);
            }
            return 'core';
        }

        // Third-party extensions
        if (count($parts) >= 2) {
            $vendor = $this->toKebabCase($parts[0]);
            $package = $this->toKebabCase($parts[1]);
            return "{$vendor}-{$package}";
        }

        return null;
    }

    /**
     * Check if a translation exists for the given key.
     */
    private function translationExists(string $key, string $locale): bool
    {
        $translator = $this->locales->getTranslator();
        return $translator->trans($key, [], null, $locale) !== $key;
    }

    /**
     * Convert camelCase/PascalCase to snake_case.
     */
    private function toSnakeCase(string $text): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $text));
    }

    /**
     * Convert PascalCase to kebab-case.
     */
    private function toKebabCase(string $text): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $text));
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
