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
- `src/Job/SendWebhookNotificationJob.php` - Queue job that sends HTTP POST to webhook URL with retry logic and exponential backoff

### Frontend (JavaScript)

- `js/src/admin/index.js` - Admin settings page for configuring webhook URL, token, timeout, and retry count

### Settings Keys

- `import-ai-webhook-notification.webhook_url` - Target webhook endpoint
- `import-ai-webhook-notification.webhook_token` - Bearer token for Authorization header
- `import-ai-webhook-notification.timeout` - Request timeout in seconds (default: 30)
- `import-ai-webhook-notification.retry_count` - Retry attempts on failure (default: 3)

### Webhook Payload Structure

Passthrough mode sends all notification data without filtering. Model objects are converted to arrays via `toArray()`.

```json
{
  "event": "notification",
  "blueprint_class": "<full_class_name>",
  "timestamp": "<ISO8601>",
  "type": "<notification_type>",
  "subject_model": "<class_name>",
  "from_user": { "id": 1, "username": "...", "display_name": "...", "email": "..." },
  "subject": { "id": 1, "discussion_id": 2, "user_id": 1, "...": "..." },
  "data": {},
  "users": [{ "id": 1, "username": "...", "display_name": "...", "email": "..." }]
}
```

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


