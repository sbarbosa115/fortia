import {useViewer} from '@console/entities/viewer';
import {api, isApiError} from '@shared/api';
import {
  Badge,
  Button,
  Card,
  CardBody,
  CardHeader,
  ConfirmDialog,
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
  type AnalyticsForm,
  analyticsFormFrom,
  analyticsPatch,
  analyticsUrlError,
  checkBody,
  ENCRYPTIONS,
  type Encryption,
  type SmtpForm,
  smtpErrors,
  smtpFormFrom,
  smtpPatch,
  type SystemSettings as Settings,
} from '../model/systemForm';
import './system-settings.css';

const settingsKey = (customerId: string) => ['system-settings', customerId];

function useSystemSettingsUrl() {
  const viewer = useViewer();
  return `/customer/${encodeURIComponent(viewer.customerId)}/system-settings`;
}

/**
 * The System tab of /profile: the account's own SMTP server (with a "Validate" that sends a test email), its own
 * OpenAI API key and its own analytics service (endpoint + key). Without them, Mappi's platform server, key and
 * service are used. Secrets are never shown back.
 */
export function SystemSettings() {
  const viewer = useViewer();
  const url = useSystemSettingsUrl();
  const query = useQuery({
    queryKey: settingsKey(viewer.customerId),
    queryFn: () => api.get<Settings>(url),
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
  return (
    <div className="system-settings stack">
      {viewer.canWrite ? null : <ReadOnlyNotice />}
      <SmtpCard
        key={`smtp-${query.data.smtp_host ?? ''}`}
        settings={query.data}
      />
      <OpenAiCard settings={query.data} />
      <AnalyticsCard
        key={`analytics-${query.data.analytics_base_url ?? ''}`}
        settings={query.data}
      />
    </div>
  );
}

function ReadOnlyNotice() {
  const {t: ts} = useTranslation('shared');
  return (
    <p className="system-settings__notice" role="note">
      {ts('readOnly.change')}
    </p>
  );
}

function useSave() {
  const viewer = useViewer();
  const url = useSystemSettingsUrl();
  const queryClient = useQueryClient();
  return (body: Record<string, unknown>) =>
    api.patch<Settings>(url, body).then((saved) => {
      queryClient.setQueryData(settingsKey(viewer.customerId), saved);
      return saved;
    });
}

function SmtpCard({settings}: {settings: Settings}) {
  const {t} = useTranslation('widgets.system-settings');
  const {t: ts} = useTranslation('shared');
  const viewer = useViewer();
  const toast = useToast();
  const url = useSystemSettingsUrl();
  const saveSettings = useSave();
  const [form, setForm] = useState<SmtpForm>(() => smtpFormFrom(settings));
  const [touched, setTouched] = useState(false);
  const [checkFailure, setCheckFailure] = useState<string | null>(null);
  const [confirmRemove, setConfirmRemove] = useState(false);

  const lockedReason = viewer.canWrite ? null : ts('readOnly.change');
  const locked = lockedReason !== null;
  const errors = smtpErrors(form);
  const valid = Object.keys(errors).length === 0;
  const saved = settings.smtp_host !== null && settings.smtp_host !== undefined;

  const save = useMutation({
    mutationFn: () => saveSettings(smtpPatch(form)),
    onSuccess: (next) => {
      setForm(smtpFormFrom(next));
      setTouched(false);
      toast.success(t('smtp.saved'));
    },
    onError: (error) => toast.apiError(error),
  });
  const remove = useMutation({
    mutationFn: () => saveSettings({smtp_host: ''}),
    onSuccess: (next) => {
      setForm(smtpFormFrom(next));
      setConfirmRemove(false);
      toast.success(t('smtp.removed'));
    },
    onError: (error) => toast.apiError(error),
  });
  const check = useMutation({
    mutationFn: () =>
      api.post<{sent_to: string}>(`${url}/smtp-check`, checkBody(form)),
    onMutate: () => setCheckFailure(null),
    onSuccess: (result) =>
      toast.success(t('smtp.checkSent', {email: result.sent_to})),
    onError: (error) => {
      if (isApiError(error) && error.code === 'SMTP_CHECK_FAILED') {
        const reason = String(error.details.reason ?? 'refused');
        setCheckFailure(
          t(`smtp.checkFailed.${reason}`, {
            defaultValue: t('smtp.checkFailed.refused'),
          }),
        );
        return;
      }
      toast.apiError(error);
    },
  });

  const set =
    <K extends keyof SmtpForm>(field: K) =>
    (value: SmtpForm[K]) => {
      setTouched(true);
      setCheckFailure(null);
      setForm((current) => ({...current, [field]: value}));
    };
  const errorText = (field: 'host' | 'port' | 'fromEmail') =>
    touched && errors[field] ? t(`smtp.errors.${errors[field]}`) : null;
  const submit = (event: FormEvent) => {
    event.preventDefault();
    setTouched(true);
    if (valid && !locked) {
      save.mutate();
    }
  };

  return (
    <form onSubmit={submit} noValidate>
      <Card>
        <CardHeader
          title={t('smtp.title')}
          actions={
            <Badge tone={saved ? 'success' : 'neutral'}>
              {saved ? t('smtp.statusOwn') : t('smtp.statusPlatform')}
            </Badge>
          }
        />
        <CardBody>
          <p className="muted system-settings__intro">{t('smtp.intro')}</p>
          <div className="grid-2">
            <Field label={t('smtp.host')} error={errorText('host')} required>
              <TextInput
                disabled={locked}
                autoComplete="off"
                placeholder="smtp.example.com"
                value={form.host}
                onChange={(e) => set('host')(e.target.value)}
              />
            </Field>
            <div className="system-settings__pair">
              <Field label={t('smtp.port')} error={errorText('port')} required>
                <TextInput
                  type="number"
                  inputMode="numeric"
                  min={1}
                  max={65535}
                  disabled={locked}
                  value={form.port}
                  onChange={(e) => set('port')(e.target.value)}
                />
              </Field>
              <Field label={t('smtp.encryption')}>
                <Select
                  disabled={locked}
                  value={form.encryption}
                  onChange={(e) =>
                    set('encryption')(e.target.value as Encryption)
                  }
                  options={ENCRYPTIONS.map((value) => ({
                    value,
                    label: t(`smtp.encryptions.${value}`),
                  }))}
                />
              </Field>
            </div>
            <Field label={t('smtp.username')} hint={t('smtp.usernameHint')}>
              <TextInput
                disabled={locked}
                autoComplete="off"
                value={form.username}
                onChange={(e) => set('username')(e.target.value)}
              />
            </Field>
            <Field
              label={t('smtp.password')}
              hint={
                settings.smtp_password_set ? t('smtp.passwordSaved') : undefined
              }
            >
              <TextInput
                type="password"
                autoComplete="new-password"
                disabled={locked}
                placeholder={settings.smtp_password_set ? '••••••••' : ''}
                value={form.password}
                onChange={(e) => set('password')(e.target.value)}
              />
            </Field>
            <Field
              label={t('smtp.fromEmail')}
              hint={t('smtp.fromEmailHint')}
              error={errorText('fromEmail')}
              required
            >
              <TextInput
                type="email"
                disabled={locked}
                placeholder="hello@example.com"
                value={form.fromEmail}
                onChange={(e) => set('fromEmail')(e.target.value)}
              />
            </Field>
            <Field label={t('smtp.fromName')}>
              <TextInput
                disabled={locked}
                maxLength={100}
                value={form.fromName}
                onChange={(e) => set('fromName')(e.target.value)}
              />
            </Field>
          </div>
          {checkFailure ? (
            <p className="system-settings__failure" role="alert">
              {checkFailure}
            </p>
          ) : null}
          <div className="row system-settings__actions">
            {saved ? (
              <Button
                variant="ghost"
                disabledReason={lockedReason}
                onClick={() => setConfirmRemove(true)}
              >
                {t('smtp.remove')}
              </Button>
            ) : null}
            <Button
              loading={check.isPending}
              disabled={!valid}
              disabledReason={lockedReason}
              onClick={() => {
                setTouched(true);
                if (valid) {
                  check.mutate();
                }
              }}
            >
              {t('smtp.check')}
            </Button>
            <Button
              type="submit"
              variant="primary"
              loading={save.isPending}
              disabled={!valid}
              disabledReason={lockedReason}
            >
              {ts('actions.save')}
            </Button>
          </div>
        </CardBody>
      </Card>
      <ConfirmDialog
        open={confirmRemove}
        title={t('smtp.removeTitle')}
        body={t('smtp.removeBody')}
        confirmLabel={t('smtp.remove')}
        danger
        loading={remove.isPending}
        onConfirm={() => remove.mutate()}
        onCancel={() => setConfirmRemove(false)}
      />
    </form>
  );
}

function OpenAiCard({settings}: {settings: Settings}) {
  const {t} = useTranslation('widgets.system-settings');
  const {t: ts} = useTranslation('shared');
  const viewer = useViewer();
  const toast = useToast();
  const saveSettings = useSave();
  const [key, setKey] = useState('');
  const [confirmRemove, setConfirmRemove] = useState(false);

  const lockedReason = viewer.canWrite ? null : ts('readOnly.change');
  const locked = lockedReason !== null;
  const typed = key.trim();

  const save = useMutation({
    mutationFn: () => saveSettings({openai_api_key: typed}),
    onSuccess: () => {
      setKey('');
      toast.success(t('openai.saved'));
    },
    onError: (error) => toast.apiError(error),
  });
  const remove = useMutation({
    mutationFn: () => saveSettings({openai_api_key: null}),
    onSuccess: () => {
      setConfirmRemove(false);
      toast.success(t('openai.removed'));
    },
    onError: (error) => toast.apiError(error),
  });
  const submit = (event: FormEvent) => {
    event.preventDefault();
    if (typed !== '' && !locked) {
      save.mutate();
    }
  };

  return (
    <form onSubmit={submit} noValidate>
      <Card>
        <CardHeader
          title={t('openai.title')}
          actions={
            <Badge tone={settings.openai_api_key_set ? 'success' : 'neutral'}>
              {settings.openai_api_key_set
                ? t('openai.statusOwn', {
                    last4: settings.openai_api_key_last4 ?? '',
                  })
                : t('openai.statusPlatform')}
            </Badge>
          }
        />
        <CardBody>
          <p className="muted system-settings__intro">{t('openai.intro')}</p>
          <Field
            label={
              settings.openai_api_key_set
                ? t('openai.replaceKey')
                : t('openai.key')
            }
            hint={t('openai.keyHint')}
          >
            <TextInput
              type="password"
              autoComplete="off"
              spellCheck={false}
              disabled={locked}
              placeholder="sk-…"
              maxLength={512}
              value={key}
              onChange={(e) => setKey(e.target.value)}
            />
          </Field>
          <div className="row system-settings__actions">
            {settings.openai_api_key_set ? (
              <Button
                variant="ghost"
                disabledReason={lockedReason}
                onClick={() => setConfirmRemove(true)}
              >
                {t('openai.remove')}
              </Button>
            ) : null}
            <Button
              type="submit"
              variant="primary"
              loading={save.isPending}
              disabled={typed === ''}
              disabledReason={lockedReason}
            >
              {ts('actions.save')}
            </Button>
          </div>
        </CardBody>
      </Card>
      <ConfirmDialog
        open={confirmRemove}
        title={t('openai.removeTitle')}
        body={t('openai.removeBody')}
        confirmLabel={t('openai.remove')}
        danger
        loading={remove.isPending}
        onConfirm={() => remove.mutate()}
        onCancel={() => setConfirmRemove(false)}
      />
    </form>
  );
}

function AnalyticsCard({settings}: {settings: Settings}) {
  const {t} = useTranslation('widgets.system-settings');
  const {t: ts} = useTranslation('shared');
  const viewer = useViewer();
  const toast = useToast();
  const saveSettings = useSave();
  const [form, setForm] = useState<AnalyticsForm>(() =>
    analyticsFormFrom(settings),
  );
  const [touched, setTouched] = useState(false);
  const [confirmRemove, setConfirmRemove] = useState(false);
  const lockedReason = viewer.canWrite ? null : ts('readOnly.change');
  const locked = lockedReason !== null;
  const urlError = analyticsUrlError(form);
  const saved = Boolean(settings.analytics_base_url);
  const keySet = settings.analytics_api_key_set;
  const changed =
    form.baseUrl.trim() !== (settings.analytics_base_url ?? '') ||
    form.apiKey.trim() !== '';

  const save = useMutation({
    mutationFn: () => saveSettings(analyticsPatch(form)),
    onSuccess: (next) => {
      setForm(analyticsFormFrom(next));
      setTouched(false);
      toast.success(t('analytics.saved'));
    },
    onError: (error) => toast.apiError(error),
  });
  const remove = useMutation({
    mutationFn: () =>
      saveSettings({analytics_base_url: null, analytics_api_key: null}),
    onSuccess: (next) => {
      setForm(analyticsFormFrom(next));
      setConfirmRemove(false);
      toast.success(t('analytics.removed'));
    },
    onError: (error) => toast.apiError(error),
  });

  const submit = (event: FormEvent) => {
    event.preventDefault();
    setTouched(true);
    if (urlError === null && changed && !locked) {
      save.mutate();
    }
  };

  return (
    <form onSubmit={submit} noValidate>
      <Card>
        <CardHeader
          title={t('analytics.title')}
          actions={
            <Badge tone={saved ? 'success' : 'neutral'}>
              {saved ? t('analytics.statusOwn') : t('analytics.statusPlatform')}
            </Badge>
          }
        />
        <CardBody>
          <p className="muted system-settings__intro">{t('analytics.intro')}</p>
          <div className="grid-2">
            <Field
              label={t('analytics.baseUrl')}
              hint={t('analytics.baseUrlHint')}
              error={
                touched && urlError ? t(`analytics.errors.${urlError}`) : null
              }
            >
              <TextInput
                type="url"
                autoComplete="off"
                spellCheck={false}
                disabled={locked}
                placeholder="https://analytics.example.com"
                maxLength={255}
                value={form.baseUrl}
                onChange={(e) => {
                  setTouched(true);
                  setForm((current) => ({...current, baseUrl: e.target.value}));
                }}
              />
            </Field>
            <Field
              label={keySet ? t('analytics.replaceKey') : t('analytics.key')}
              hint={
                keySet
                  ? t('analytics.keySaved', {
                      last4: settings.analytics_api_key_last4 ?? '',
                    })
                  : t('analytics.keyHint')
              }
            >
              <TextInput
                type="password"
                autoComplete="new-password"
                spellCheck={false}
                disabled={locked}
                placeholder={keySet ? '••••••••' : ''}
                maxLength={512}
                value={form.apiKey}
                onChange={(e) =>
                  setForm((current) => ({...current, apiKey: e.target.value}))
                }
              />
            </Field>
          </div>
          <div className="row system-settings__actions">
            {saved || keySet ? (
              <Button
                variant="ghost"
                disabledReason={lockedReason}
                onClick={() => setConfirmRemove(true)}
              >
                {t('analytics.remove')}
              </Button>
            ) : null}
            <Button
              type="submit"
              variant="primary"
              loading={save.isPending}
              disabled={!changed || urlError !== null}
              disabledReason={lockedReason}
            >
              {ts('actions.save')}
            </Button>
          </div>
        </CardBody>
      </Card>
      <ConfirmDialog
        open={confirmRemove}
        title={t('analytics.removeTitle')}
        body={t('analytics.removeBody')}
        confirmLabel={t('analytics.remove')}
        danger
        loading={remove.isPending}
        onConfirm={() => remove.mutate()}
        onCancel={() => setConfirmRemove(false)}
      />
    </form>
  );
}
