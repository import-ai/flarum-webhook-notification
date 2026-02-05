# Flarum Webhook Notification

This file provides guidance to developers when working with code in this repository.

## Overview

This is a Flarum extension that adds a webhook notification channel. When notifications are triggered in Flarum, this extension sends them to a configured HTTP endpoint.

## Development Commands

### Frontend (JavaScript)

```bash
cd js
npm install          # Install dependencies
npm run dev          # Watch mode for development
npm run build        # Production build
```

## Architecture

### Backend (PHP)

- `extend.php` - Registers the notification driver, admin frontend, locales, and settings defaults
- `src/Driver/WebhookNotificationDriver.php` - Implements `NotificationDriverInterface`, passthrough all notifications to webhook without filtering
- `src/Job/SendWebhookNotificationJob.php` - Queue job that sends HTTP POST to webhook URL

### Frontend (JavaScript)

- `js/src/admin/index.js` - Admin settings page for configuring webhook URL, token, timeout, channel icon, and channel label
- `js/src/forum/index.js` - User notification preferences for enabling/disabling webhook per notification type

### Settings Keys

- `import-ai-webhook-notification.webhook_url` - Target webhook endpoint
- `import-ai-webhook-notification.webhook_token` - Bearer token for Authorization header
- `import-ai-webhook-notification.timeout` - Request timeout in seconds (default: 30)
- `import-ai-webhook-notification.channel_icon` - Icon class for the notification channel (default: `fas fa-globe`)
- `import-ai-webhook-notification.channel_label` - Label for the notification channel (default: `Webhook`)

### Webhook Payload Structure

Passthrough mode sends all notification data without filtering. Model objects are converted to arrays via `toArray()`. Only users who have enabled webhook notifications for the specific notification type are included in the payload.

```json
{
  "event": "notification",
  "timestamp": "<ISO8601>",
  "type": "<notification_type>",
  "subject_model": "<class_name>",
  "from_user": { "id": 1, "username": "...", "display_name": "...", "email": "..." },
  "subject": { "id": 1, "discussion_id": 2, "user_id": 1, "...": "..." },
  "data": {},
  "users": [
    {
      "id": 1,
      "username": "...",
      "display_name": "...",
      "email": "...",
      "lang": "en",
      "title": "Someone posts in a discussion I'm following"
    }
  ]
}
```

#### Per-User Localization

Each user object in the `users` array includes:
- `lang`: The user's preferred locale (e.g., `en`, `zh-Hans`)
- `title`: The notification title translated to the user's preferred language

The titles are generated using the same translation keys used in Flarum's notification settings page:

| Type | Translation Key (Flarum Core/Extensions) |
|------|------------------------------------------|
| `discussionRenamed` | `core.forum.settings.notify_discussion_renamed_label` |
| `newPost` | `flarum-subscriptions.forum.settings.notify_new_post_label` |
| `postMentioned` | `flarum-mentions.forum.settings.notify_post_mentioned_label` |
| `userMentioned` | `flarum-mentions.forum.settings.notify_user_mentioned_label` |
| `groupMentioned` | `flarum-mentions.forum.settings.notify_group_mentioned_label` |
| `discussionLocked` | `flarum-lock.forum.settings.notify_discussion_locked_label` |
| `postLiked` | `flarum-likes.forum.settings.notify_post_liked_label` |

If a user's locale preference is not set, the forum's default locale is used. The extension automatically switches the translator locale for each user group to generate appropriate titles.

#### How Notification Titles Work

The extension generates notification titles using the **same translation keys** that Flarum uses for the notification preferences UI (Settings > Notifications). These keys follow this pattern:

```
{extension}.forum.settings.notify_{notification_type}_label
```

For example:
- `core.forum.settings.notify_discussion_renamed_label` → "Someone renames a discussion I started"
- `flarum-subscriptions.forum.settings.notify_new_post_label` → "Someone posts in a discussion I'm following"

These translations are already defined by extensions in their locale files (`locale/en.yml`, etc.). The webhook extension simply reuses them.

#### Supporting Custom Notification Types

When new notification types are added by extensions, they work automatically if the extension follows Flarum's convention of defining the notification preference label using the standard pattern.

**Auto-Discovery (Works Automatically)**

The extension tries to find the translation key using these strategies (in order):

1. **Type prefix**: If type contains a dot (`my-extension.myType`), extract the prefix
2. **Subject model namespace**: Derive extension from the subject model class:
   - `Flarum\Discussion\...` → `core`
   - `Flarum\Subscriptions\...` → `flarum-subscriptions`
   - `Vendor\ExtensionName\...` → `vendor-extension-name`
3. **Blueprint namespace**: Check the blueprint's own namespace
4. **Common prefixes**: Try known extension prefixes as fallback

The standard pattern checked is:
```
{extension}.forum.settings.notify_{snake_case_type}_label
```

**Example**: An extension `acme/my-extension` with:
- Notification type: `customAlert`
- Subject model: `Acme\MyExtension\Model\Post`

Would automatically find:
```
acme-my-extension.forum.settings.notify_custom_alert_label
```

**Extension Registration (If Auto-Discovery Fails)**

If your extension uses a non-standard naming convention, register explicitly:

```php
// In your extension's extend.php
use ImportAI\WebhookNotification\Extend\NotificationTitle;

return [
    (new NotificationTitle())
        ->type('myNotification', 'my-extension.forum.settings.my_custom_label_key'),
];
```

**Dynamic Registration (Advanced)**

For dynamic translation keys based on blueprint data:

```php
use ImportAI\WebhookNotification\Service\NotificationTitleRegistry;

$registry->register('myNotification', function ($blueprint) {
    // Return translation key based on blueprint data
    return 'my-extension.notification.' . $blueprint::getType();
});
```

**Fallback Behavior**

If no translation key is found:
```
Receive "{type}" notifications via webhook
```

### User Notification Preferences

The extension registers a notification preference for each notification type, allowing users to individually enable or disable webhook notifications. By default, webhook notifications are enabled for all notification types. Users can configure these preferences in their account settings.

## Git Commit Guidelines

**Format**: `type(scope): Description`

**Types**:

- `feat` - New features
- `fix` - Bug fixes
- `docs` - Documentation changes
- `style` - Styling changes
- `refactor` - Code refactoring
- `perf` - Performance improvements
- `test` - Test additions or changes
- `chore` - Maintenance tasks
- `revert` - Revert previous commits
- `build` - Build system changes

**Rules**:

- Scope is required (e.g., `sidebar`, `tasks`, `auth`)
- Description in sentence case with capital first letter
- Use present tense action verbs (Add, Fix, Support, Update, Replace, Optimize)
- No period at the end
- Keep it concise and focused

**Examples**:

```
feat(apple): Support apple signin
fix(sidebar): Change the abnormal scrolling
chore(children): Optimize children api
refactor(tasks): Add timeout status
```

**Do NOT include**:

- "Generated with Claude Code" or similar attribution
- "Co-Authored-By: Claude" or any Claude co-author tags


