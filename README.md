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
- `src/Service/NotificationTitleRegistry.php` - Registry for notification title translation keys with auto-discovery
- `src/Extend/NotificationTitle.php` - Extender for registering custom notification titles

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
  "title": "Hello World",
  "content": "This is the post excerpt (first 200 chars)...",
  "url": "http://example.com/d/123-hello-world/5",
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
      "title": "John posted"
    }
  ]
}
```

#### Payload Fields

| Field | Description |
|-------|-------------|
| `event` | Always "notification" |
| `timestamp` | ISO8601 timestamp of when the notification was triggered |
| `type` | Notification type (e.g., `newPost`, `postMentioned`) |
| `subject_model` | Class name of the subject (e.g., `Flarum\Post\Post`) |
| `title` | Discussion/post title |
| `content` | Post content excerpt (first 200 characters, plain text) |
| `url` | Direct link to the discussion/post |
| `from_user` | User who triggered the notification |
| `subject` | The notification subject (post/discussion object) |
| `data` | Additional data from the blueprint (e.g., `postNumber`) |
| `users` | Array of users who should receive this notification |

#### Per-User Localization

Each user object in the `users` array includes:
- `lang`: The user's preferred locale (e.g., `en`, `zh-Hans`)
- `title`: The notification title translated to the user's preferred language

The titles are generated using the **same translation keys** as Flarum's notification dropdown UI:

| Type | Translation Key | English Text |
|------|-----------------|--------------|
| `discussionRenamed` | `core.forum.notifications.discussion_renamed_text` | "{username} changed the title" |
| `newPost` | `flarum-subscriptions.forum.notifications.new_post_text` | "{username} posted" |
| `postMentioned` | `flarum-mentions.forum.notifications.post_mentioned_text` | "{username} replied to your post" |
| `userMentioned` | `flarum-mentions.forum.notifications.user_mentioned_text` | "{username} mentioned you" |
| `groupMentioned` | `flarum-mentions.forum.notifications.group_mentioned_text` | "{username} mentioned a group you're a member of" |
| `discussionLocked` | `flarum-lock.forum.notifications.discussion_locked_text` | "{username} locked" |
| `postLiked` | `flarum-likes.forum.notifications.post_liked_text` | "{username} liked your post" |
| `userSuspended` | `flarum-suspend.forum.notifications.user_suspended_text` | "You have been suspended for {timeReadable}" |
| `userUnsuspended` | `flarum-suspend.forum.notifications.user_unsuspended_text` | "You have been unsuspended" |

If a user's locale preference is not set, the forum's default locale is used. The extension automatically switches the translator locale for each user group to generate appropriate titles.

#### Supporting Custom Notification Types

When new notification types are added by extensions, they work automatically if the extension follows Flarum's convention of defining the notification dropdown text.

**Auto-Discovery (Works Automatically)**

The extension tries to find the translation key using these strategies (in order):

1. **Type prefix**: If type contains a dot (`my-extension.myType`), extract the prefix
2. **Subject model namespace**: Derive extension from the subject model class:
   - `Flarum\Discussion\...` → `core`
   - `Flarum\Subscriptions\...` → `flarum-subscriptions`
   - `Vendor\ExtensionName\...` → `vendor-extension-name`
3. **Blueprint namespace**: Check the blueprint's own namespace

The standard pattern checked is:
```
{extension}.forum.notifications.{snake_case_type}_text
```

**Example**: An extension `acme/my-extension` with:
- Notification type: `customAlert`
- Subject model: `Acme\MyExtension\Model\Post`
- Translation key in locale file: `acme-my-extension.forum.notifications.custom_alert_text`

Would automatically find the translation.

**Extension Registration (If Auto-Discovery Fails)**

If your extension uses a non-standard naming convention, register explicitly:

```php
// In your extension's extend.php
use ImportAI\WebhookNotification\Extend\NotificationTitle;

return [
    (new NotificationTitle())
        ->type('myNotification', 'my-extension.forum.notifications.my_custom_text'),
];
```

**Dynamic Registration (Advanced)**

For dynamic translation keys based on blueprint data:

```php
use ImportAI\WebhookNotification\Service\NotificationTitleRegistry;

$registry->register('myNotification', function ($blueprint) {
    // Return translation key based on blueprint data
    return 'my-extension.notifications.' . $blueprint::getType();
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
