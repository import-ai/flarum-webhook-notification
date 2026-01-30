import app from 'flarum/admin/app';

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
    })
    .registerSetting({
      setting: 'import-ai-webhook-notification.retry_count',
      label: app.translator.trans('import-ai-webhook-notification.admin.settings.retry_count_label'),
      help: app.translator.trans('import-ai-webhook-notification.admin.settings.retry_count_help'),
      type: 'number',
      placeholder: '3',
    });
});
