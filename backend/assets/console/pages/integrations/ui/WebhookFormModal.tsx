import {Button, Field, Modal, Select, TextInput, useToast} from '@shared/ui';
import {useMutation, useQueryClient} from '@tanstack/react-query';
import {type FormEvent, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {
  createWebhook,
  updateWebhook,
  type Webhook,
  WEBHOOKS_QUERY_KEY,
} from '../api/integrations';
import {webhookUrlError} from '../model/integrations';

const URL_ERRORS = {
  required: 'webhooks.form.urlRequired',
  https: 'webhooks.form.urlHttps',
  invalid: 'webhooks.form.urlInvalid',
} as const;

/** Add or edit a webhook (PRD §10.17): the URL must start with https://; one event and one method. */
export function WebhookFormModal({
  webhook,
  onClose,
}: {
  /** null = a new webhook. */
  webhook: Webhook | null;
  onClose: () => void;
}) {
  const {t} = useTranslation('pages.integrations');
  const {t: tShared} = useTranslation('shared');
  const toast = useToast();
  const queryClient = useQueryClient();
  const [url, setUrl] = useState(webhook?.url ?? '');
  const [touched, setTouched] = useState(false);

  const save = useMutation({
    mutationFn: () =>
      webhook
        ? updateWebhook(webhook.id, {url: url.trim()})
        : createWebhook({
            url: url.trim(),
            event_type: 'questionnaire.completed',
            method: 'POST',
          }),
    onSuccess: async () => {
      toast.success(t(webhook ? 'webhooks.updated' : 'webhooks.created'));
      await queryClient.invalidateQueries({queryKey: WEBHOOKS_QUERY_KEY});
      onClose();
    },
    onError: (error) => toast.apiError(error),
  });

  const error = webhookUrlError(url);
  const submit = (event: FormEvent) => {
    event.preventDefault();
    setTouched(true);
    if (error === null) {
      save.mutate();
    }
  };

  return (
    <Modal
      open
      title={t(
        webhook ? 'webhooks.form.editTitle' : 'webhooks.form.createTitle',
      )}
      onClose={onClose}
      footer={
        <>
          <Button onClick={onClose}>{tShared('actions.cancel')}</Button>
          <Button
            variant="primary"
            type="submit"
            form="webhook-form"
            loading={save.isPending}
          >
            {t(
              webhook
                ? 'webhooks.form.submitEdit'
                : 'webhooks.form.submitCreate',
            )}
          </Button>
        </>
      }
    >
      <form id="webhook-form" className="stack" onSubmit={submit} noValidate>
        <Field
          label={t('webhooks.form.url')}
          hint={t('webhooks.form.urlHint')}
          required
          error={touched && error ? t(URL_ERRORS[error]) : null}
        >
          <TextInput
            type="url"
            inputMode="url"
            value={url}
            maxLength={2048}
            placeholder={t('webhooks.form.urlPlaceholder')}
            onChange={(event) => setUrl(event.target.value)}
            onBlur={() => setTouched(true)}
          />
        </Field>
        <div className="grid-2">
          <Field label={t('webhooks.form.event')}>
            <Select
              value="questionnaire.completed"
              disabled
              options={[
                {
                  value: 'questionnaire.completed',
                  label: t('webhooks.responseCompleted'),
                },
              ]}
            />
          </Field>
          <Field label={t('webhooks.form.method')}>
            <Select
              value="POST"
              disabled
              options={[{value: 'POST', label: 'POST'}]}
            />
          </Field>
        </div>
      </form>
    </Modal>
  );
}
