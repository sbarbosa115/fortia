import {matchesAllWords} from '@shared/lib';
import {ErrorState, Icon} from '@shared/ui';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import type {ProjectWizardState} from '../model/useProjectWizard';
import {NEW_ORGANIZATION} from '../model/wizard';
import {AudienceSelector} from './AudienceSelector';
import {CreateNewButton} from './CreateNewButton';
import {NewOrganizationDialog} from './NewOrganizationDialog';
import {type Option, OptionList} from './OptionList';

/** Step 2 (PRD §10.12): the organization (listed, or created here and saved on Create), who answers, and the name. */
export function OrganizationStep({wizard}: {wizard: ProjectWizardState}) {
  const {t} = useTranslation('pages.project-new');
  const [search, setSearch] = useState('');
  const created = wizard.newOrganization;

  const members = (count: number, domain?: string | null) =>
    [domain || t('organization.noDomain'), t('summary.members', {count})].join(
      ' · ',
    );
  const listed: Option[] = wizard.organizations
    .filter((o) => matchesAllWords(`${o.name} ${o.domain_email ?? ''}`, search))
    .map((o) => ({
      id: o.organization_id,
      name: o.name,
      detail: members(o.organization_users.length, o.domain_email),
      isNew: false,
    }));
  const options: Option[] = created
    ? [
        {
          id: NEW_ORGANIZATION,
          name: created.name.trim(),
          detail: members(created.members.length, created.domain.trim()),
          isNew: true,
        },
        ...listed,
      ]
    : listed;

  return (
    <div className="prj-new__stack">
      <section className="prj-new__card" aria-labelledby="prj-new-organization">
        <h2 id="prj-new-organization" className="prj-new__card-title">
          {t('organization.question')}
        </h2>
        <label className="prj-new__search">
          <span className="visually-hidden">{t('organization.search')}</span>
          <Icon name="search" size={16} />
          <input
            type="search"
            className="prj-new__input prj-new__input--search"
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            placeholder={t('organization.search')}
            autoComplete="off"
          />
        </label>

        {wizard.organizationsLoading ? (
          <p className="prj-new__loading">
            <span className="prj-new__spin">
              <Icon name="loader" size={14} />
            </span>
            {t('loading')}
          </p>
        ) : wizard.organizationsError ? (
          <ErrorState
            error={wizard.organizationsError}
            onRetry={wizard.retryOrganizations}
          />
        ) : (
          <OptionList
            label={t('organization.listLabel')}
            options={options}
            selected={wizard.organization?.id ?? null}
            onSelect={wizard.chooseOrganization}
            newLabel={t('summary.newFemale')}
            emptyLabel={t('organization.none')}
          />
        )}

        <CreateNewButton
          title={t('organization.create')}
          hint={t('organization.createHint')}
          onClick={wizard.openOrganizationDialog}
        />
      </section>

      {wizard.organization ? (
        <section className="prj-new__card" aria-label={t('audience.label')}>
          <AudienceSelector
            members={wizard.organization.members}
            value={wizard.audience}
            onChange={wizard.setAudience}
          />
          <div className="prj-new__name-field">
            <label
              htmlFor="prj-new-assignation-name"
              className="prj-new__label"
            >
              {t('organization.assignationName')}
            </label>
            <input
              id="prj-new-assignation-name"
              className="prj-new__input"
              value={wizard.assignationName}
              maxLength={200}
              required
              aria-describedby="prj-new-assignation-name-hint"
              onChange={(event) =>
                wizard.setAssignationName(event.target.value)
              }
            />
            <p
              id="prj-new-assignation-name-hint"
              className="prj-new__small-muted"
            >
              {t('organization.assignationNameHint')}
            </p>
          </div>
          {wizard.copyFrom !== null ? (
            <p className="prj-new__warning" role="status">
              <Icon name="alert" size={16} />
              {t('organization.copyWarning', {organization: wizard.copyFrom})}
            </p>
          ) : null}
        </section>
      ) : null}

      {wizard.organizationDialog ? (
        <NewOrganizationDialog
          initial={created}
          initialName={search.trim()}
          onSave={(value) => {
            setSearch('');
            wizard.saveNewOrganization(value);
          }}
          onClose={wizard.closeOrganizationDialog}
        />
      ) : null}
    </div>
  );
}
