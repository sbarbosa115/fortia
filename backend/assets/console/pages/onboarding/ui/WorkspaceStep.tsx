import {Button, Field, Select, TextInput} from '@shared/ui';
import type {FormEvent} from 'react';
import {useTranslation} from 'react-i18next';
import {NAMESPACE, type Onboarding} from '../model/useOnboarding';
import {
  type AccountLanguage,
  canLeaveWorkspace,
  isValidWebsite,
  WORKSPACE_NAME_MAX,
} from '../model/wizard';

const LANGUAGES: AccountLanguage[] = ['es-CO', 'en-US'];

/** Step 2 (D15): the workspace's name, language and website, saved with PATCH /customer/workspace. */
export function WorkspaceStep({onboarding}: {onboarding: Onboarding}) {
  const {t} = useTranslation(NAMESPACE);
  const {state, dispatch, canWrite, savingWorkspace} = onboarding;
  const {workspace} = state;
  const websiteError =
    workspace.website.trim() !== '' && !isValidWebsite(workspace.website)
      ? t('workspace.websiteInvalid')
      : null;
  const nameError =
    workspace.name.trim().length > WORKSPACE_NAME_MAX
      ? t('workspace.nameTooLong')
      : null;

  const submit = (event: FormEvent) => {
    event.preventDefault();
    if (canWrite && canLeaveWorkspace(workspace)) {
      onboarding.saveWorkspace();
    }
  };

  return (
    <form
      className="onboarding__section"
      aria-labelledby="onb-workspace"
      onSubmit={submit}
      noValidate
    >
      <h1 id="onb-workspace" className="serif-heading onboarding__title">
        {t('workspace.title')}
      </h1>
      <p className="muted">{t('workspace.subtitle')}</p>
      <div className="card onboarding__card stack">
        <Field label={t('workspace.name')} required error={nameError}>
          <TextInput
            value={workspace.name}
            placeholder={t('workspace.namePlaceholder')}
            autoComplete="organization"
            onChange={(event) =>
              dispatch({
                type: 'editWorkspace',
                workspace: {name: event.target.value},
              })
            }
          />
        </Field>
        <Field
          label={t('workspace.language')}
          hint={t('workspace.languageHint')}
        >
          <Select
            value={workspace.language}
            options={LANGUAGES.map((language) => ({
              value: language,
              label: t(`workspace.languages.${language}`),
            }))}
            onChange={(event) =>
              dispatch({
                type: 'editWorkspace',
                workspace: {language: event.target.value as AccountLanguage},
              })
            }
          />
        </Field>
        <Field label={t('workspace.website')} error={websiteError}>
          <TextInput
            type="url"
            inputMode="url"
            value={workspace.website}
            placeholder={t('workspace.websitePlaceholder')}
            autoComplete="url"
            onChange={(event) =>
              dispatch({
                type: 'editWorkspace',
                workspace: {website: event.target.value},
              })
            }
          />
        </Field>
      </div>
      <div className="onboarding__actions">
        <Button onClick={() => dispatch({type: 'goTo', step: 1})}>
          {t('actions.back')}
        </Button>
        <Button
          type="submit"
          variant="primary"
          loading={savingWorkspace}
          disabled={!canLeaveWorkspace(workspace)}
          disabledReason={
            canWrite ? null : t('readOnly.change', {ns: 'shared'})
          }
        >
          {t('actions.continue')}
        </Button>
      </div>
    </form>
  );
}
