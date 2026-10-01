import {
  Button,
  Field,
  Icon,
  Modal,
  Select,
  TextInput,
  useToast,
} from '@shared/ui';
import {useMutation, useQueryClient} from '@tanstack/react-query';
import {type FormEvent, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {API_KEYS_QUERY_KEY, createApiKey} from '../api/integrations';
import {
  DEFAULT_EXPIRATION,
  EXPIRATION_CHOICES,
  type ExpirationChoice,
  expirationDays,
  KEY_NAME_MAX,
  keyNameError,
} from '../model/integrations';
import {useCopy} from './CodeBlock';

/**
 * "Create API key" (PRD §10.17): name (required, ≤ 100) and expiration (7 / 30 / 60 / 90 days or Never, 7 by
 * default). On success the plaintext key is shown once, with a Copy button; closing the dialog forgets it.
 */
export function CreateApiKeyModal({
  open,
  onClose,
}: {
  open: boolean;
  onClose: () => void;
}) {
  const {t} = useTranslation('pages.integrations');
  const {t: tShared} = useTranslation('shared');
  const toast = useToast();
  const copy = useCopy();
  const queryClient = useQueryClient();
  const [name, setName] = useState('');
  const [expiration, setExpiration] =
    useState<ExpirationChoice>(DEFAULT_EXPIRATION);
  const [touched, setTouched] = useState(false);
  const [key, setKey] = useState<string | null>(null);

  const create = useMutation({
    mutationFn: () =>
      createApiKey({
        name: name.trim(),
        expiration_days: expirationDays(expiration),
      }),
    onSuccess: async (plaintext) => {
      setKey(plaintext);
      toast.success(t('keys.reveal.created'));
      await queryClient.invalidateQueries({queryKey: API_KEYS_QUERY_KEY});
    },
    onError: (error) => toast.apiError(error),
  });

  const close = () => {
    setName('');
    setExpiration(DEFAULT_EXPIRATION);
    setTouched(false);
    setKey(null);
    create.reset();
    onClose();
  };

  const nameError = keyNameError(name);
  const submit = (event: FormEvent) => {
    event.preventDefault();
    setTouched(true);
    if (nameError === null) {
      create.mutate();
    }
  };

  if (key !== null) {
    return (
      <Modal
        open={open}
        title={t('keys.reveal.title')}
        onClose={close}
        footer={
          <Button variant="primary" onClick={close}>
            {t('keys.reveal.done')}
          </Button>
        }
      >
        <div className="stack">
          <p className="int-warning" role="note">
            {t('keys.reveal.warning')}
          </p>
          <Field label={t('keys.reveal.label')}>
            <TextInput
              readOnly
              value={key}
              className="int-mono"
              onFocus={(event) => event.currentTarget.select()}
            />
          </Field>
          <div>
            <Button
              icon={<Icon name="copy" size={16} />}
              onClick={() => copy(key)}
            >
              {t('copy')}
            </Button>
          </div>
        </div>
      </Modal>
    );
  }

  return (
    <Modal
      open={open}
      title={t('keys.form.title')}
      onClose={close}
      footer={
        <>
          <Button onClick={close}>{tShared('actions.cancel')}</Button>
          <Button
            variant="primary"
            type="submit"
            form="create-api-key"
            loading={create.isPending}
          >
            {t('keys.form.submit')}
          </Button>
        </>
      }
    >
      <form id="create-api-key" className="stack" onSubmit={submit} noValidate>
        <Field
          label={t('keys.form.name')}
          required
          error={
            touched && nameError
              ? t(
                  nameError === 'required'
                    ? 'keys.form.nameRequired'
                    : 'keys.form.nameTooLong',
                )
              : null
          }
        >
          <TextInput
            value={name}
            maxLength={KEY_NAME_MAX}
            placeholder={t('keys.form.namePlaceholder')}
            onChange={(event) => setName(event.target.value)}
            onBlur={() => setTouched(true)}
          />
        </Field>
        <Field label={t('keys.form.expiration')}>
          <Select
            value={expiration}
            onChange={(event) =>
              setExpiration(event.target.value as ExpirationChoice)
            }
            options={EXPIRATION_CHOICES.map((choice) => ({
              value: choice,
              label:
                choice === 'never'
                  ? t('keys.form.never')
                  : t('keys.form.days', {count: Number(choice)}),
            }))}
          />
        </Field>
      </form>
    </Modal>
  );
}
