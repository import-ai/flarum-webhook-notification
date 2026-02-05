<?php

namespace ImportAI\WebhookNotification\Extend;

use Flarum\Extension\Extension;
use Flarum\Foundation\ContainerUtil;
use Illuminate\Contracts\Container\Container;
use ImportAI\WebhookNotification\Service\NotificationTitleRegistry;

/**
 * Extender for registering webhook notification titles.
 *
 * Example usage:
 *
 *     (new ImportAI\WebhookNotification\Extend\NotificationTitle())
 *         ->type('myNotification', 'my-extension.forum.settings.notify_my_notification_label')
 *
 * Or with callable:
 *
 *     (new ImportAI\WebhookNotification\Extend\NotificationTitle())
 *         ->type('myNotification', function ($blueprint) {
 *             return 'my-extension.custom.title';
 *         })
 */
class NotificationTitle implements \Flarum\Extend\ExtenderInterface
{
    private array $types = [];

    /**
     * Register a translation key for a notification type.
     *
     * @param string $type The notification type (e.g., 'newPost')
     * @param string|\Closure $translationKey The translation key, or a callback that returns it
     *        Callback signature: function (BlueprintInterface $blueprint): string
     */
    public function type(string $type, $translationKey): self
    {
        $this->types[$type] = $translationKey;

        return $this;
    }

    public function extend(Container $container, ?Extension $extension = null): void
    {
        if (!empty($this->types)) {
            $container
                ->extend(NotificationTitleRegistry::class, function (NotificationTitleRegistry $registry, Container $container) {
                    foreach ($this->types as $type => $translationKey) {
                        if ($translationKey instanceof \Closure) {
                            $translationKey = ContainerUtil::wrapCallback($translationKey, $container);
                        }
                        $registry->register($type, $translationKey);
                    }

                    return $registry;
                });
        }
    }
}
