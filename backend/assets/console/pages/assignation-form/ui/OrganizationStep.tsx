import {matchesAllWords} from '@shared/lib';
import {ErrorState, Icon} from '@shared/ui';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import type {AssignationWizardState} from '../model/useAssignationWizard';
import {NEW_ORGANIZATION} from '../model/wizard';
import {CreateNewButton} from './CreateNewButton';
import {NewOrganizationDialog} from './NewOrganizationDialog';
import {type Option, OptionList} from './OptionList';

/**
 * Step 2: the organization the questionnaires go to — one of the account's, searched, or a new one created here and
 * saved on Create. Everybody in it responds.
 */
export function OrganizationStep({wizard}: {wizard: AssignationWizardState}) {
  const {t} = useTranslation('pages.assignation-form');
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
    <div className="asg-wiz__stack">
      <section className="asg-wiz__card" aria-labelledby="asg-wiz-organization">
        <h2 id="asg-wiz-organization" className="asg-wiz__card-title">
          {t('organization.question')}
        </h2>
        <label className="asg-wiz__search">
          <span className="visually-hidden">{t('organization.search')}</span>
          <Icon name="search" size={16} />
          <input
            type="search"
            className="asg-wiz__input asg-wiz__input--search"
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            placeholder={t('organization.search')}
            autoComplete="off"
          />
        </label>

        {wizard.organizationsLoading ? (
          <p className="asg-wiz__loading">
            <span className="asg-wiz__spin">
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
            newLabel={t('summary.new')}
            emptyLabel={t('organization.none')}
          />
        )}

        <CreateNewButton
          title={t('organization.create')}
          hint={t('organization.createHint')}
          onClick={wizard.openOrganizationDialog}
        />
      </section>

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
