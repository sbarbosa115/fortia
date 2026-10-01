import {Button, Field, Icon, TextInput, useToast} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {NAMESPACE, type Onboarding} from '../model/useOnboarding';

/**
 * Step 7: the share link (copies) and the exits: Customize more, Invite team, Go to dashboard. Every exit first
 * sets the onboarding flag; when that fails the page says so and stays.
 */
export function ResultStep({onboarding}: {onboarding: Onboarding}) {
  const {t} = useTranslation(NAMESPACE);
  const toast = useToast();
  const {state, finish, finishing, publicUrl} = onboarding;
  const link = new URL(
    publicUrl(state.slug),
    window.location.origin,
  ).toString();

  const copy = () => {
    const done = navigator.clipboard?.writeText(link);
    if (!done) {
      toast.error(t('result.copyFailed'));
      return;
    }
    done.then(
      () => toast.success(t('result.copied')),
      () => toast.error(t('result.copyFailed')),
    );
  };

  return (
    <section className="onboarding__section" aria-labelledby="onb-result">
      <h1 id="onb-result" className="serif-heading onboarding__title">
        {t('result.title')}
      </h1>
      <p className="muted">{t('result.subtitle')}</p>
      <div className="card onboarding__card stack">
        <Field label={t('result.linkLabel')}>
          <TextInput value={link} readOnly />
        </Field>
        <div className="row">
          <Button icon={<Icon name="copy" />} onClick={copy}>
            {t('result.share')}
          </Button>
        </div>
      </div>
      <div className="onboarding__actions">
        <Button
          loading={finishing === 'customize'}
          disabled={finishing !== null}
          icon={<Icon name="palette" />}
          onClick={() => finish('customize')}
        >
          {t('result.customize')}
        </Button>
        <Button
          loading={finishing === 'invite'}
          disabled={finishing !== null}
          icon={<Icon name="users" />}
          onClick={() => finish('invite')}
        >
          {t('result.invite')}
        </Button>
        <Button
          variant="primary"
          loading={finishing === 'dashboard'}
          disabled={finishing !== null}
          onClick={() => finish('dashboard')}
        >
          {t('result.dashboard')}
        </Button>
      </div>
    </section>
  );
}
