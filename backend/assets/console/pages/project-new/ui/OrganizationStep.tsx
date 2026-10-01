import {AudiencePicker} from '@console/entities/assignation';
import {matchesAllWords} from '@shared/lib';
import {
  Card,
  CardBody,
  ErrorState,
  Field,
  LoadingState,
  SearchInput,
  TextInput,
} from '@shared/ui';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import type {ProjectWizardState} from '../model/useProjectWizard';
import {NEW_ORGANIZATION} from '../model/wizard';
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
    .filter(
      (o) =>
        o.organization_id === wizard.organization?.id ||
        matchesAllWords(`${o.name} ${o.domain_email ?? ''}`, search),
    )
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
      <Card>
        <CardBody>
          <div className="prj-new__stack">
            <h3 className="prj-new__card-title" id="prj-new-organization">
              {t('organization.question')}
            </h3>
            <SearchInput
              value={search}
              onChange={setSearch}
              label={t('organization.search')}
              placeholder={t('organization.search')}
            />
            {wizard.organizationsLoading ? (
              <LoadingState />
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
                emptyLabel={t('organization.none')}
              />
            )}
            <button
              type="button"
              className="prj-new__create"
              onClick={wizard.openOrganizationDialog}
            >
              <span className="prj-new__create-title">
                + {t('organization.create')}
              </span>
              <span className="muted">{t('organization.createHint')}</span>
            </button>
          </div>
        </CardBody>
      </Card>

      {wizard.organization ? (
        <Card>
          <CardBody>
            <div className="prj-new__stack">
              <AudiencePicker
                members={wizard.organization.members}
                value={wizard.audience}
                onChange={wizard.setAudience}
                error={null}
              />
              <Field
                label={t('organization.assignationName')}
                hint={t('organization.assignationNameHint')}
                required
              >
                <TextInput
                  value={wizard.assignationName}
                  maxLength={200}
                  onChange={(event) =>
                    wizard.setAssignationName(event.target.value)
                  }
                />
              </Field>
              {wizard.copyFrom !== null ? (
                <p className="prj-new__notice" role="status">
                  {t('organization.copyWarning', {
                    organization: wizard.copyFrom,
                  })}
                </p>
              ) : null}
            </div>
          </CardBody>
        </Card>
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
