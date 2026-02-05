<?php

namespace ImportAI\WebhookNotification\Service;

use Flarum\Locale\LocaleManager;
use Flarum\Notification\Blueprint\BlueprintInterface;

/**
 * Registry for notification type title translation keys.
 * Allows extensions to register custom notification labels.
 *
 * This uses the same translation keys as Flarum's notification dropdown UI:
 *   {extension}.forum.notifications.{type}_text
 *
 * Examples:
 *   - core.forum.notifications.discussion_renamed_text
 *   - flarum-subscriptions.forum.notifications.new_post_text
 *   - flarum-mentions.forum.notifications.post_mentioned_text
 */
class NotificationTitleRegistry
{
    /**
     * Map of notification types to their translation keys.
     */
    private array $translationKeys = [];

    /**
     * Default translation keys for known Flarum core and bundled extensions.
     * These are the keys used by Flarum's notification dropdown UI.
     */
    private const DEFAULT_KEYS = [
        // Core notifications
        'discussionRenamed' => 'core.forum.notifications.discussion_renamed_text',

        // flarum/subscriptions
        'newPost' => 'flarum-subscriptions.forum.notifications.new_post_text',

        // flarum/mentions
        'postMentioned' => 'flarum-mentions.forum.notifications.post_mentioned_text',
        'userMentioned' => 'flarum-mentions.forum.notifications.user_mentioned_text',
        'groupMentioned' => 'flarum-mentions.forum.notifications.group_mentioned_text',

        // flarum/lock
        'discussionLocked' => 'flarum-lock.forum.notifications.discussion_locked_text',

        // flarum/likes
        'postLiked' => 'flarum-likes.forum.notifications.post_liked_text',

        // flarum/suspend
        'userSuspended' => 'flarum-suspend.forum.notifications.user_suspended_text',
        'userUnsuspended' => 'flarum-suspend.forum.notifications.user_unsuspended_text',
    ];

    public function __construct(
        protected LocaleManager $locales
    ) {
        $this->translationKeys = self::DEFAULT_KEYS;
    }

    /**
     * Register a translation key for a notification type.
     *
     * @param string $type The notification type (e.g., 'newPost')
     * @param string|\Closure $translationKey The translation key, or a callback that receives the blueprint and returns the key
     *        Callback signature: function (BlueprintInterface $blueprint): string
     */
    public function register(string $type, $translationKey): void
    {
        $this->translationKeys[$type] = $translationKey;
    }

    /**
     * Get the translation key for a notification type.
     *
     * @param string $type The notification type
     * @param BlueprintInterface|null $blueprint The blueprint instance (required if registered with callback)
     * @return string|null The translation key, or null if not registered
     */
    public function getTranslationKey(string $type, ?BlueprintInterface $blueprint = null): ?string
    {
        $key = $this->translationKeys[$type] ?? null;

        // If key is a callback, resolve it
        if ($key instanceof \Closure && $blueprint !== null) {
            return $key($blueprint);
        }

        return $key;
    }

    /**
     * Attempt to auto-discover the translation key using common conventions.
     *
     * This method tries to find translation keys by:
     * 1. Checking if the type contains a vendor prefix (e.g., "vendor-extension.type")
     * 2. Deriving the extension name from the subject model namespace
     * 3. Checking the blueprint class namespace itself
     * 4. Trying common patterns with known extension prefixes as fallback
     *
     * Flarum extensions define notification dropdown text using this pattern:
     *   {extension}.forum.notifications.{snake_case_type}_text
     *
     * Examples:
     *   - core.forum.notifications.discussion_renamed_text
     *   - flarum-subscriptions.forum.notifications.new_post_text
     *   - my-vendor.my-extension.forum.notifications.custom_alert_text
     *
     * @param string $type The notification type
     * @param string|null $subjectModel The subject model class name (e.g., 'Flarum\Discussion\Discussion')
     * @param string|null $blueprintClass The blueprint class name for additional namespace hints
     * @return string|null The discovered translation key, or null if not found
     */
    public function discoverTranslationKey(string $type, ?string $subjectModel = null, ?string $blueprintClass = null): ?string
    {
        // Convert type to snake_case for pattern matching
        $snakeType = $this->toSnakeCase($type);

        // Build a list of extension prefixes to try
        $prefixes = [];

        // 1. If type contains a dot, it might already include the extension prefix
        // e.g., "my-extension.myNotification" -> try "my-extension"
        if (str_contains($type, '.')) {
            $parts = explode('.', $type);
            $prefixes[] = $parts[0];
        }

        // 2. Try to derive extension from subject model namespace
        // e.g., "Vendor\ExtensionName\Model" -> "vendor-extension-name"
        if ($subjectModel !== null) {
            $derivedPrefix = $this->deriveExtensionFromNamespace($subjectModel);
            if ($derivedPrefix !== null) {
                $prefixes[] = $derivedPrefix;
            }
        }

        // 3. Try to derive extension from blueprint class namespace
        // This is useful when the blueprint is in the extension's namespace
        if ($blueprintClass !== null) {
            $derivedPrefix = $this->deriveExtensionFromNamespace($blueprintClass);
            if ($derivedPrefix !== null && !in_array($derivedPrefix, $prefixes)) {
                $prefixes[] = $derivedPrefix;
            }
        }

        // Remove duplicates while preserving order
        $prefixes = array_unique($prefixes);

        foreach ($prefixes as $ext) {
            // Try notification text pattern: {type}_text (snake_case)
            $key = "{$ext}.forum.notifications.{$snakeType}_text";
            if ($this->translationExists($key)) {
                return $key;
            }

            // Try original pattern: {Type}_text
            $key = "{$ext}.forum.notifications.{$type}_text";
            if ($this->translationExists($key)) {
                return $key;
            }
        }

        // 5. Also try settings label pattern as fallback
        foreach ($prefixes as $ext) {
            // Try snake_case pattern: notify_{type}_label
            $key = "{$ext}.forum.settings.notify_{$snakeType}_label";
            if ($this->translationExists($key)) {
                return $key;
            }

            // Try original pattern: notify_{Type}_label
            $key = "{$ext}.forum.settings.notify_{$type}_label";
            if ($this->translationExists($key)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Derive the extension name from a model's namespace.
     *
     * Examples:
     * - "Flarum\Discussion\Discussion" -> "core"
     * - "Vendor\ExtensionName\Model" -> "vendor-extension-name"
     * - "Flarum\Subscriptions\Notification\NewPostBlueprint" -> "flarum-subscriptions"
     *
     * @param string $className The fully qualified class name
     * @return string|null The derived extension prefix, or null if cannot be determined
     */
    private function deriveExtensionFromNamespace(string $className): ?string
    {
        // Handle Flarum core classes
        if (str_starts_with($className, 'Flarum\\')) {
            $parts = explode('\\', $className);

            // "Flarum\Discussion\..." or "Flarum\Post\..." -> core
            if (count($parts) >= 2 && in_array($parts[1], ['Discussion', 'Post', 'User', 'Group'])) {
                return 'core';
            }

            // "Flarum\Subscriptions\..." -> flarum-subscriptions
            if (count($parts) >= 2 && $parts[1] !== 'Core') {
                return 'flarum-' . $this->toKebabCase($parts[1]);
            }

            return 'core';
        }

        // Handle third-party extensions: "Vendor\ExtensionName\..."
        $parts = explode('\\', $className);
        if (count($parts) >= 2) {
            $vendor = $this->toKebabCase($parts[0]);
            $package = $this->toKebabCase($parts[1]);
            return "{$vendor}-{$package}";
        }

        return null;
    }

    /**
     * Get the translated title for a notification type.
     *
     * Uses the same translation keys as Flarum's notification dropdown UI.
     * Extensions define these keys in their locale files as:
     *   {extension}.forum.notifications.{snake_case_type}_text
     *
     * @param string $type The notification type
     * @param array $params Translation parameters
     * @param string|null $locale Optional locale override
     * @param string|null $subjectModel The subject model class name for auto-discovery hints
     * @param string|null $blueprintClass The blueprint class name for auto-discovery hints
     * @param BlueprintInterface|null $blueprint The blueprint instance (required if using callback registration)
     * @return string The translated title
     */
    public function getTitle(string $type, array $params = [], ?string $locale = null, ?string $subjectModel = null, ?string $blueprintClass = null, ?BlueprintInterface $blueprint = null): string
    {
        $translator = $this->locales->getTranslator();

        // Store current locale if we need to switch
        $originalLocale = null;
        if ($locale !== null && $locale !== $this->locales->getLocale()) {
            $originalLocale = $this->locales->getLocale();
            $this->locales->setLocale($locale);
        }

        try {
            // First, check registered keys (explicit registration takes priority)
            $key = $this->getTranslationKey($type, $blueprint);

            // If not registered, try auto-discovery
            if ($key === null) {
                $key = $this->discoverTranslationKey($type, $subjectModel, $blueprintClass);

                // Cache the discovered key for future use
                if ($key !== null) {
                    $this->translationKeys[$type] = $key;
                }
            }

            // If we have a key, translate it
            if ($key !== null) {
                $title = $translator->trans($key, $params);

                // If translation exists (doesn't return the key itself), use it
                if ($title !== $key) {
                    return $title;
                }
            }

            // Fallback to generic template using the notification type
            return $translator->trans('core.forum.settings.notification_checkbox_a11y_label_template', [
                '{description}' => $type,
                '{method}' => 'webhook',
            ]);
        } finally {
            // Restore original locale if we changed it
            if ($originalLocale !== null) {
                $this->locales->setLocale($originalLocale);
            }
        }
    }

    /**
     * Check if a translation exists for the given key.
     */
    private function translationExists(string $key): bool
    {
        $translator = $this->locales->getTranslator();
        $translation = $translator->trans($key);

        // If the translator returns the key itself, the translation doesn't exist
        return $translation !== $key;
    }

    /**
     * Convert PascalCase to kebab-case.
     */
    private function toKebabCase(string $text): string
    {
        $text = preg_replace('/([a-z])([A-Z])/', '$1-$2', $text);
        return strtolower($text);
    }

    /**
     * Convert camelCase/PascalCase to snake_case.
     */
    private function toSnakeCase(string $text): string
    {
        // Handle PascalCase/camelCase
        $text = preg_replace('/([a-z])([A-Z])/', '$1_$2', $text);
        return strtolower($text);
    }
}
