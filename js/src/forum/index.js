import { extend } from 'flarum/common/extend';
import app from 'flarum/forum/app';
import NotificationGrid from 'flarum/forum/components/NotificationGrid';

app.initializers.add('import-ai-webhook-notification', () => {
  extend(NotificationGrid.prototype, 'notificationMethods', function (items) {
    items.add('webhook', {
      name: 'webhook',
      icon: 'fas fa-globe',
      label: app.translator.trans('import-ai-webhook-notification.forum.settings.notify_webhook_label')
    });
  });
});
