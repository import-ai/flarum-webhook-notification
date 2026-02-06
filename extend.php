<?php

namespace ImportAI\WebhookNotification;

use Flarum\Extend;
use ImportAI\WebhookNotification\Driver\WebhookNotificationDriver;

return [
    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js'),

    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js'),

    new Extend\Locales(__DIR__ . '/locale'),

    (new Extend\Notification())
        ->driver('webhook', WebhookNotificationDriver::class),

    (new Extend\Settings())
        ->default('import-ai-webhook-notification.webhook_url', '')
        ->default('import-ai-webhook-notification.webhook_token', '')
        ->default('import-ai-webhook-notification.timeout', 30)
        ->default('import-ai-webhook-notification.channel_icon', 'fas fa-globe')
        ->default('import-ai-webhook-notification.channel_label', 'Webhook')
        ->serializeToForum('webhookNotificationIcon', 'import-ai-webhook-notification.channel_icon')
        ->serializeToForum('webhookNotificationLabel', 'import-ai-webhook-notification.channel_label'),
];
