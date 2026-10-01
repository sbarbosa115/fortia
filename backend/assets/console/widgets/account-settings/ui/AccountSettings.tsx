import {useViewer} from '@console/entities/viewer';
import {api} from '@shared/api';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  ErrorState,
  Field,
  LoadingState,
  Select,
  TextInput,
  useToast,
} from '@shared/ui';
import {useMutation, useQuery, useQueryClient} from '@tanstack/react-query';
import {type FormEvent, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {
  buildPatch,
  type CustomerSettings,
  formFrom,
  isValidForm,
  isValidMaxFiles,
  type Language,
  type SettingsForm,
  TRACKING_FIELDS,
  TRACKING_MAX,
  type TrackingField,
} from '../model/settingsForm';
import './account-settings.css';

const settingsKey = (customerId: string) => ['account-settings', customerId];

/**
 * The settings of /profile (PRD §10.14): account language, maximum files per question and the tracking ids.
 * Changing them needs write permission; every disabled control says why.
 */
export function AccountSettings() {
  const viewer = useViewer();
  const query = useQuery({
    queryKey: settingsKey(viewer.customerId),
    queryFn: () =>
      api.get<CustomerSettings>(
        `/customer/${encodeURIComponent(viewer.customerId)}/settings`,
      ),
    enabled: viewer.customerId !== '',
  });
  if (query.isPending) {
    return <LoadingState />;
  }
  if (query.isError) {
    return (
      <ErrorState error={query.error} onRetry={() => void query.refetch()} />
    );
  }
  return <SettingsEditor key={viewer.customerId} settings={query.data} />;
}

function SettingsEditor({settings}: {settings: CustomerSettings}) {
  const {t} = useTranslation('widgets.account-settings');
  const {t: ts} = useTranslation('shared');
  const viewer = useViewer();
  const toast = useToast();
  const queryClient = useQueryClient();
  const [initial, setInitial] = useState<SettingsForm>(() =>
    formFrom(settings),
  );
  const [form, setForm] = useState<SettingsForm>(initial);

  const save = useMutation({
    mutationFn: () =>
      api.patch<CustomerSettings>(
        `/customer/${encodeURIComponent(viewer.customerId)}/settings`,
        buildPatch(initial, form),
      ),
    onSuccess: (saved) => {
      queryClient.setQueryData(settingsKey(viewer.customerId), saved);
      const next = formFrom(saved);
      setInitial(next);
      setForm(next);
      toast.success(t('saved'));
    },
    onError: (error) => toast.apiError(error),
  });

  const lockedReason = viewer.canWrite ? null : ts('readOnly.change');
  const locked = lockedReason !== null;
  const maxFilesError = isValidMaxFiles(form.max_files)
    ? null
    : t('errors.maxFiles');
  const valid = isValidForm(form);

  const set = (field: keyof SettingsForm) => (value: string) =>
    setForm((current) => ({...current, [field]: value}));
  const submit = (event: FormEvent) => {
    event.preventDefault();
    if (valid && !locked) {
      save.mutate();
    }
  };
  const trackingError = (field: TrackingField) =>
    form[field].trim().length > TRACKING_MAX
      ? t('errors.tracking', {max: TRACKING_MAX})
      : null;

  return (
    <form className="account-settings stack" onSubmit={submit} noValidate>
      {lockedReason ? (
        <p className="account-settings__notice" role="note">
          {lockedReason}
        </p>
      ) : null}
      <Card>
        <CardHeader title={t('account.title')} />
        <CardBody>
          <div className="stack">
            <Field
              label={t('account.language')}
              hint={t('account.languageHint')}
            >
              <Select
                value={form.language}
                disabled={locked}
                onChange={(e) => set('language')(e.target.value as Language)}
                options={[
                  {value: 'es-CO', label: t('languages.es-CO')},
                  {value: 'en-US', label: t('languages.en-US')},
                ]}
              />
            </Field>
            <Field
              label={t('account.maxFiles')}
              hint={t('account.maxFilesHint')}
              error={maxFilesError}
            >
              <TextInput
                type="number"
                inputMode="numeric"
                min={1}
                max={20}
                step={1}
                className="account-settings__number"
                disabled={locked}
                value={form.max_files}
                onChange={(e) => set('max_files')(e.target.value)}
              />
            </Field>
          </div>
        </CardBody>
      </Card>
      <Card>
        <CardHeader title={t('tracking.title')} />
        <CardBody>
          <p className="muted account-settings__intro">{t('tracking.intro')}</p>
          <div className="grid-2">
            {TRACKING_FIELDS.map((field) => (
              <Field
                key={field}
                label={t(`tracking.fields.${field}`)}
                error={trackingError(field)}
              >
                <TextInput
                  disabled={locked}
                  maxLength={TRACKING_MAX}
                  placeholder={t(`tracking.placeholders.${field}`)}
                  value={form[field]}
                  onChange={(e) => set(field)(e.target.value)}
                />
              </Field>
            ))}
          </div>
        </CardBody>
      </Card>
      <div className="row account-settings__actions">
        <Button
          type="submit"
          variant="primary"
          loading={save.isPending}
          disabled={!valid}
          disabledReason={lockedReason}
        >
          {ts('actions.saveChanges')}
        </Button>
      </div>
    </form>
  );
}
