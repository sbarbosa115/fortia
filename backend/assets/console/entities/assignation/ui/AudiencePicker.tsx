import type {Audience} from '../api/assignations';
import {
  type AudienceMember,
  audienceMembers,
  distinctValues,
} from '../lib/audience';
import {fold, matchesAllWords} from '@shared/lib';
import {Checkbox, ChoiceCards, SearchInput} from '@shared/ui';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import './audience-picker.css';

const TYPES: Audience['type'][] = ['all', 'members', 'area', 'role'];

/**
 * "Who responds?" (PRD §10.11): Everybody; People (checkboxes, searched by name, email, area or role); Area or Role
 * (their distinct values with a count of people). A live counter says how many will respond. Disabled until an
 * organization is chosen (`members` null).
 */
export function AudiencePicker({
  members: memberList,
  value,
  onChange,
  error,
}: {
  members: AudienceMember[] | null;
  value: Audience;
  onChange: (audience: Audience) => void;
  error: string | null;
}) {
  const {t} = useTranslation('entities.assignation');
  const [search, setSearch] = useState('');
  const ready = memberList !== null;
  const members = memberList ?? [];
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
    <fieldset className="audience-picker" disabled={!ready}>
      <legend className="field__label">{t('picker.label')}</legend>
      {ready ? null : <p className="muted">{t('picker.needsOrganization')}</p>}
      <ChoiceCards
        label={t('picker.label')}
        value={value.type}
        onChange={(type) => onChange({type, values: []})}
        choices={TYPES.map((type) => ({
          value: type,
          title: t(`picker.${type}`),
          body: t(`picker.${type}Body`),
          disabled: !ready,
        }))}
      />
      {ready && value.type === 'members' ? (
        <div className="audience-picker__panel">
          <SearchInput
            value={search}
            onChange={setSearch}
            label={t('picker.search')}
            placeholder={t('picker.searchPlaceholder')}
          />
          <div className="audience-picker__checks">
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
      {ready && (value.type === 'area' || value.type === 'role') ? (
        <div className="audience-picker__panel audience-picker__checks">
          {distinctValues(members, value.type).length === 0 ? (
            <p className="muted">{t('picker.noValues')}</p>
          ) : (
            distinctValues(members, value.type).map((item) => (
              <Checkbox
                key={item.value}
                label={t('picker.valueCount', {
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
      {ready ? (
        <p className="audience-picker__count" aria-live="polite">
          {t('picker.count', {count})}
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
