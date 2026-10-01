import {ImportMembersCsv} from '@console/features/import-members-csv';
import {useDocumentTitle} from '@shared/lib';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  EmptyState,
  ErrorState,
  Field,
  Icon,
  LoadingState,
  PageHeader,
  TextArea,
  TextInput,
  Toggle,
} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {Link, useParams} from 'react-router';
import {useOrganizationForm} from '../model/useOrganizationForm';
import {MemberRows} from './MemberRows';
import './organization-form.css';

/** /organizations/new and /organizations/:id/edit (PRD §10.10): the organization's fields and its member list. */
export function OrganizationFormPage() {
  const {t} = useTranslation('pages.organization-form');
  const {t: tShared} = useTranslation('shared');
  const {id} = useParams();
  const state = useOrganizationForm(id);
  const title = state.editing ? t('editTitle') : t('newTitle');
  useDocumentTitle(`Mappi - ${title}`);

  if (state.loading) {
    return <LoadingState />;
  }
  if (state.loadError) {
    return (
      <Card>
        <ErrorState error={state.loadError} onRetry={state.retry} />
      </Card>
    );
  }
  if (state.notFound) {
    return (
      <Card>
        <EmptyState
          title={tShared('errors.ORGANIZATION_NOT_FOUND')}
          action={
            <Link className="btn btn--secondary" to="/organizations">
              {t('backToList')}
            </Link>
          }
        />
      </Card>
    );
  }

  const {form, errors} = state;
  return (
    <form
      className="org-form"
      noValidate
      onSubmit={(event) => {
        event.preventDefault();
        state.submit();
      }}
    >
      <PageHeader
        title={title}
        subtitle={t('subtitle')}
        actions={
          <>
            <Button type="button" onClick={state.cancel}>
              {tShared('actions.cancel')}
            </Button>
            <Button
              type="submit"
              variant="primary"
              loading={state.saving}
              icon={<Icon name="check" size={16} />}
            >
              {state.editing ? tShared('actions.saveChanges') : t('create')}
            </Button>
          </>
        }
      />
      <div className="stack">
        <Card>
          <CardHeader title={t('details')} />
          <CardBody>
            <div className="stack">
              <Field
                label={t('name')}
                required
                error={
                  state.showErrors && errors.name
                    ? t(`errors.${errors.name}`)
                    : null
                }
              >
                <TextInput
                  value={form.name}
                  maxLength={120}
                  onChange={(event) =>
                    state.setField({name: event.target.value})
                  }
                />
              </Field>
              <div className="grid-2">
                <Field label={t('domain')} hint={t('domainHint')}>
                  <TextInput
                    value={form.domain}
                    placeholder={t('domainPlaceholder')}
                    spellCheck={false}
                    autoCapitalize="none"
                    onChange={(event) =>
                      state.setField({domain: event.target.value})
                    }
                  />
                </Field>
                <div className="org-form__active">
                  <Toggle
                    checked={form.active}
                    label={t('active')}
                    onChange={(active) => state.setField({active})}
                  />
                </div>
              </div>
              <Field label={t('description')}>
                <TextArea
                  value={form.description}
                  maxLength={1000}
                  onChange={(event) =>
                    state.setField({description: event.target.value})
                  }
                />
              </Field>
            </div>
          </CardBody>
        </Card>
        <Card>
          <CardHeader
            title={t('members.title', {count: form.members.length})}
            actions={
              <Button
                type="button"
                size="sm"
                icon={<Icon name="plus" size={16} />}
                onClick={state.addMember}
              >
                {t('members.add')}
              </Button>
            }
          />
          <CardBody>
            <div className="stack">
              <ImportMembersCsv
                existing={form.members}
                onImport={state.importMembers}
              />
              {state.domainWarning > 0 ? (
                <p className="org-form__warning" role="status">
                  <Icon name="alert" size={16} />
                  {t('domainWarning', {
                    count: state.domainWarning,
                    domain: form.domain.trim().toLowerCase(),
                  })}
                </p>
              ) : null}
              {state.apiRows.length > 0 ? (
                <ul className="org-form__api-errors" role="alert">
                  {state.apiRows.map((row) => (
                    <li key={`${row.position}-${row.detail}`}>
                      {t('memberError', {
                        position: row.position,
                        name: row.name || t('members.noName'),
                        detail: row.detail,
                      })}
                    </li>
                  ))}
                </ul>
              ) : null}
              <MemberRows
                members={form.members}
                errors={errors.members}
                showErrors={state.showErrors}
                onChange={state.changeMember}
                onBlur={state.normalizeMember}
                onRemove={state.removeMember}
              />
            </div>
          </CardBody>
        </Card>
      </div>
    </form>
  );
}
