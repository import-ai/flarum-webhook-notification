import app from 'flarum/admin/app';
import { extend } from 'flarum/common/extend';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';

app.initializers.add('import-ai-webhook-notification', function() {
  app.extensionData
    .for('import-ai-webhook-notification')
    .registerSetting({
      setting: 'import-ai-webhook-notification.webhook_url',
      label: app.translator.trans('import-ai-webhook-notification.admin.settings.webhook_url_label'),
      help: app.translator.trans('import-ai-webhook-notification.admin.settings.webhook_url_help'),
      type: 'url',
      placeholder: 'https://example.com/webhook',
    })
    .registerSetting({
      setting: 'import-ai-webhook-notification.webhook_token',
      label: app.translator.trans('import-ai-webhook-notification.admin.settings.webhook_token_label'),
      help: app.translator.trans('import-ai-webhook-notification.admin.settings.webhook_token_help'),
      type: 'password',
    })
    .registerSetting({
      setting: 'import-ai-webhook-notification.timeout',
      label: app.translator.trans('import-ai-webhook-notification.admin.settings.timeout_label'),
      help: app.translator.trans('import-ai-webhook-notification.admin.settings.timeout_help'),
      type: 'number',
      placeholder: '30',
      min: 1,
      max: 300,
    })
    .registerSetting({
      setting: 'import-ai-webhook-notification.retry_count',
      label: app.translator.trans('import-ai-webhook-notification.admin.settings.retry_count_label'),
      help: app.translator.trans('import-ai-webhook-notification.admin.settings.retry_count_help'),
      type: 'number',
      placeholder: '3',
      min: 0,
      max: 10,
    })
    .registerSetting({
      setting: 'import-ai-webhook-notification.channel_icon',
      label: app.translator.trans('import-ai-webhook-notification.admin.settings.channel_icon_label'),
      help: [
        m('a', { href: 'https://fontawesome.com/v5/search?m=free', target: '_blank' }, 'Font Awesome'),
        app.translator.trans('import-ai-webhook-notification.admin.settings.channel_icon_help')
      ],
      type: 'text',
      placeholder: 'fas fa-globe',
    })
    .registerSetting({
      setting: 'import-ai-webhook-notification.channel_label',
      label: app.translator.trans('import-ai-webhook-notification.admin.settings.channel_label_label'),
      help: app.translator.trans('import-ai-webhook-notification.admin.settings.channel_label_help'),
      type: 'text',
      placeholder: 'Webhook',
    });

  // Add validation before saving
  extend(ExtensionPage.prototype, 'saveSettings', function(promise, event) {
    if (this.extension.id !== 'import-ai-webhook-notification') return;

    const settings = this.settings || {};
    const timeout = parseInt(settings['import-ai-webhook-notification.timeout']?.() || '30', 10);
    const retryCount = parseInt(settings['import-ai-webhook-notification.retry_count']?.() || '3', 10);
    const channelIcon = settings['import-ai-webhook-notification.channel_icon']?.() || '';
    const channelLabel = settings['import-ai-webhook-notification.channel_label']?.() || '';

    const errors = [];

    // Validate timeout
    if (isNaN(timeout) || timeout < 1 || timeout > 300) {
      errors.push(app.translator.trans('import-ai-webhook-notification.admin.validation.timeout_invalid'));
    }

    // Validate retry count
    if (isNaN(retryCount) || retryCount < 0 || retryCount > 10) {
      errors.push(app.translator.trans('import-ai-webhook-notification.admin.validation.retry_count_invalid'));
    }

    // Validate channel icon format (if provided)
    if (channelIcon && !/^(fas|far|fab|fal|fad)\s+fa-[\w-]+$/.test(channelIcon)) {
      errors.push(app.translator.trans('import-ai-webhook-notification.admin.validation.channel_icon_invalid'));
    }

    // Validate channel label (if icon is set, label should be set too)
    if (channelIcon && !channelLabel.trim()) {
      errors.push(app.translator.trans('import-ai-webhook-notification.admin.validation.channel_label_required'));
    }

    if (errors.length > 0) {
      event.preventDefault();
      app.alerts.show({ type: 'error' }, errors.join('\n'));
      return Promise.reject();
    }
  });
});
