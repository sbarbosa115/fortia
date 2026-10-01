import {errorMessageKey, isApiError} from '@shared/api';
import {SLUG_PATTERN} from '@shared/lib';
import {Button, Field, TextInput} from '@shared/ui';
import {type FormEvent, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {NAMESPACE, type Onboarding} from '../model/useOnboarding';

const SLUG_MAX = 100;

/** Step 5: the editable slug after {FRONTEND_URL}/f/; "Publish and open test" activates it and opens the test. */
export function PublishStep({onboarding}: {onboarding: Onboarding}) {
  const {t} = useTranslation(NAMESPACE);
  const {state, canWrite, publishing, publishError, publicUrl} = onboarding;
  const [slug, setSlug] = useState(state.slug);
  const trimmed = slug.trim();
  const valid = SLUG_PATTERN.test(trimmed) && trimmed.length <= SLUG_MAX;
  const prefix = publicUrl('');
  const error =
    trimmed !== '' && !valid
      ? t('publish.slugInvalid')
      : isApiError(publishError) &&
          publishError.code === 'SLUG_ALREADY_IN_USE' &&
          !publishing
        ? t(errorMessageKey(publishError), {ns: 'shared'})
        : null;

  const submit = (event: FormEvent) => {
    event.preventDefault();
    if (valid && canWrite && !publishing) {
      onboarding.publish(trimmed);
    }
  };

  return (
    <form
      className="onboarding__section"
      aria-labelledby="onb-publish"
      onSubmit={submit}
      noValidate
    >
      <h1 id="onb-publish" className="serif-heading onboarding__title">
        {t('publish.title')}
      </h1>
      <p className="muted">{t('publish.subtitle')}</p>
      <div className="card onboarding__card stack">
        <Field
          label={t('publish.slug')}
          hint={t('publish.slugHint')}
          error={error}
        >
          <TextInput
            value={slug}
            maxLength={SLUG_MAX}
            spellCheck={false}
            autoCapitalize="none"
            onChange={(event) => setSlug(event.target.value.toLowerCase())}
          />
        </Field>
        <p className="onboarding__url">
          <span className="muted">{prefix}</span>
          <strong>{trimmed}</strong>
        </p>
      </div>
      <div className="onboarding__actions">
        <Button
          type="submit"
          variant="primary"
          loading={publishing}
          disabled={!valid}
          disabledReason={
            canWrite ? null : t('readOnly.change', {ns: 'shared'})
          }
        >
          {t('publish.publish')}
        </Button>
      </div>
    </form>
  );
}
