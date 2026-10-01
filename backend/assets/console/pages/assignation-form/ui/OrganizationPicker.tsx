import type {Organization} from '@console/entities/organization';
import {matchesAllWords} from '@shared/lib';
import {Field, SearchInput} from '@shared/ui';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {Link} from 'react-router';

/**
 * Organization* (PRD §10.11): a list of the account's organizations with a client-side search over every word,
 * ignoring case and accents. One radio per organization.
 */
export function OrganizationPicker({
  organizations,
  value,
  onChange,
  error,
}: {
  organizations: Organization[];
  value: string;
  onChange: (organizationId: string) => void;
  error: string | null;
}) {
  const {t} = useTranslation('pages.assignation-form');
  const [search, setSearch] = useState('');
  const shown = organizations.filter(
    (o) => o.organization_id === value || matchesAllWords(o.name, search),
  );

  if (organizations.length === 0) {
    return (
      <Field label={t('organization.label')} required error={error}>
        <p className="muted">
          {t('organization.noneYet')}{' '}
          <Link to="/organizations/new">{t('organization.create')}</Link>
        </p>
      </Field>
    );
  }
  return (
    <fieldset className="asg-form__fieldset">
      <legend className="field__label">
        {t('organization.label')}
        <span className="field__required" aria-hidden>
          *
        </span>
      </legend>
      <SearchInput
        value={search}
        onChange={setSearch}
        label={t('organization.search')}
        placeholder={t('organization.placeholder')}
      />
      <div
        className="asg-form__options"
        role="radiogroup"
        aria-label={t('organization.choose')}
      >
        {shown.length === 0 ? (
          <p className="muted">{t('organization.none')}</p>
        ) : (
          shown.map((organization) => (
            <label
              key={organization.organization_id}
              className="asg-form__option"
            >
              <input
                type="radio"
                name="organization"
                checked={organization.organization_id === value}
                onChange={() => onChange(organization.organization_id)}
              />
              <span>{organization.name}</span>
              <span className="muted">
                {t('organization.members', {
                  count: organization.organization_users.length,
                })}
              </span>
            </label>
          ))
        )}
      </div>
      {error ? (
        <span className="field__error" role="alert">
          {error}
        </span>
      ) : null}
    </fieldset>
  );
}
