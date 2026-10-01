import {
  type Audience,
  audienceMembers,
  distinctValues,
} from '@console/entities/assignation';
import type {Organization} from '@console/entities/organization';
import {fold, matchesAllWords} from '@shared/lib';
import {Checkbox, ChoiceCards, SearchInput} from '@shared/ui';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';

const TYPES: Audience['type'][] = ['all', 'members', 'area', 'role'];

/**
 * "Who responds?" (PRD §10.11): Everybody; People (checkboxes, searched by name, email, area or role); Area or Role
 * (their distinct values with a count of people). A live counter says how many will respond. Disabled until an
 * organization is chosen.
 */
export function AudiencePicker({
  organization,
  value,
  onChange,
  error,
}: {
  organization: Organization | null;
  value: Audience;
  onChange: (audience: Audience) => void;
  error: string | null;
}) {
  const {t} = useTranslation('pages.assignation-form');
  const [search, setSearch] = useState('');
  const members = organization?.organization_users ?? [];
  const count = audienceMembers(value, members).length;

  const toggle = (item: string, checked: boolean) => {
    const values = checked
      ? [...value.values, item]
      : value.values.filter((v) => fold(v) !== fold(item));
    onChange({type: value.type, values});
  };
  const checked = (item: string) =>
    value.values.some((v) => fold(v) === fold(item));

  return (
    <fieldset className="asg-form__fieldset" disabled={!organization}>
      <legend className="field__label">{t('audience.label')}</legend>
      {organization ? null : (
        <p className="muted">{t('audience.needsOrganization')}</p>
      )}
      <ChoiceCards
        label={t('audience.label')}
        value={value.type}
        onChange={(type) => onChange({type, values: []})}
        choices={TYPES.map((type) => ({
          value: type,
          title: t(`audience.${type}`),
          body: t(`audience.${type}Body`),
          disabled: !organization,
        }))}
      />
      {organization && value.type === 'members' ? (
        <div className="asg-form__panel">
          <SearchInput
            value={search}
            onChange={setSearch}
            label={t('audience.search')}
            placeholder={t('audience.searchPlaceholder')}
          />
          <div className="asg-form__checks">
            {members
              .filter((m) =>
                matchesAllWords(
                  [m.name, m.email ?? '', m.area ?? '', m.role ?? ''].join(' '),
                  search,
                ),
              )
              .map((member) => (
                <Checkbox
                  key={member.organization_user_id}
                  label={
                    <>
                      {member.name}
                      {member.email ? (
                        <span className="muted"> · {member.email}</span>
                      ) : null}
                    </>
                  }
                  checked={checked(member.organization_user_id)}
                  onChange={(event) =>
                    toggle(member.organization_user_id, event.target.checked)
                  }
                />
              ))}
          </div>
        </div>
      ) : null}
      {organization && (value.type === 'area' || value.type === 'role') ? (
        <div className="asg-form__panel asg-form__checks">
          {distinctValues(members, value.type).length === 0 ? (
            <p className="muted">{t('audience.noValues')}</p>
          ) : (
            distinctValues(members, value.type).map((item) => (
              <Checkbox
                key={item.value}
                label={t('audience.valueCount', {
                  value: item.value,
                  count: item.count,
                })}
                checked={checked(item.value)}
                onChange={(event) => toggle(item.value, event.target.checked)}
              />
            ))
          )}
        </div>
      ) : null}
      {organization ? (
        <p className="asg-form__count" aria-live="polite">
          {t('audience.count', {count})}
        </p>
      ) : null}
      {error ? (
        <span className="field__error" role="alert">
          {error}
        </span>
      ) : null}
    </fieldset>
  );
}
