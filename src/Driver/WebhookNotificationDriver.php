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

        // Get excerpt from subject if available (e.g., post content)
        $excerpt = $this->getExcerpt($blueprint);

        // Get URL to the subject
        $subjectUrl = $this->getSubjectUrl($blueprint);

        // Group users by their locale preference
        $usersByLocale = $this->groupUsersByLocale($filteredUsers);

        // Generate per-user data with localized titles
        $usersWithLang = [];

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
                'title' => $titleParams['{title}'] ?? null,
                'content' => $excerpt,
                'url' => $subjectUrl,
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

        // Add from_user display name (used by notification text translations)
        if ($fromUser) {
            $params['{from_user}'] = $fromUser->display_name;
            $params['{username}'] = $fromUser->display_name;
            $params['user'] = $fromUser;
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

    /**
     * Extract excerpt from the notification subject (e.g., post content).
     */
    private function getExcerpt(BlueprintInterface $blueprint): ?string
    {
        $subject = $blueprint->getSubject();

        if (!$subject) {
            return null;
        }

        // Try to get content from the subject (posts have content)
        $content = null;

        if (method_exists($subject, 'getAttribute')) {
            $content = $subject->getAttribute('content');
        }

        if ($content === null && isset($subject->content)) {
            $content = $subject->content;
        }

        // Try contentPlain method (available in Flarum 2.0+)
        if ($content === null && method_exists($subject, 'contentPlain')) {
            $content = $subject->contentPlain();
        }

        if ($content === null) {
            return null;
        }

        // Truncate to 200 characters (matching Flarum's frontend)
        $excerpt = strip_tags($content);
        if (strlen($excerpt) > 200) {
            $excerpt = substr($excerpt, 0, 197) . '...';
        }

        return $excerpt;
    }

    /**
     * Build the URL to the notification subject.
     */
    private function getSubjectUrl(BlueprintInterface $blueprint): ?string
    {
        $subject = $blueprint->getSubject();
        $data = $blueprint->getData() ?? [];

        if (!$subject) {
            return null;
        }

        // Get base URL from settings
        $baseUrl = $this->settings->get('forum_canonical_url') ?? $this->settings->get('url');

        // Build URL based on subject type
        if (method_exists($subject, 'discussion')) {
            // It's a post - link to discussion with post number
            $discussion = $subject->discussion;
            if ($discussion) {
                $postNumber = $data['postNumber'] ?? $data['replyNumber'] ?? $subject->number ?? null;
                $slug = $this->slugify($discussion->title);
                $url = "{$baseUrl}/d/{$discussion->id}-{$slug}";
                if ($postNumber) {
                    $url .= "/{$postNumber}";
                }
                return $url;
            }
        }

        // It's a discussion
        if (method_exists($subject, 'getAttribute') && $subject->getAttribute('title')) {
            $slug = $this->slugify($subject->title);
            $postNumber = $data['postNumber'] ?? null;
            $url = "{$baseUrl}/d/{$subject->id}-{$slug}";
            if ($postNumber) {
                $url .= "/{$postNumber}";
            }
            return $url;
        }

        return $baseUrl;
    }

    /**
     * Create a URL-friendly slug from a string.
     */
    private function slugify(string $text): string
    {
        // Convert to lowercase and replace spaces with hyphens
        $text = strtolower($text);
        $text = preg_replace('/[^\w\s-]/', '', $text);
        $text = preg_replace('/[\s]+/', '-', $text);
        $text = trim($text, '-');
        return $text;
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
