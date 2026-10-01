import {
  type Audience,
  type AudienceMember,
  audienceMembers,
  distinctValues,
} from '@console/entities/assignation';
import {fold, joinClasses, matchesAllWords} from '@shared/lib';
import {Icon} from '@shared/ui';
import {useId, useState} from 'react';
import {useTranslation} from 'react-i18next';

const TYPES: Audience['type'][] = ['all', 'members', 'area', 'role'];

type Member = AudienceMember & {phone?: string | null};

type Choice = {
  value: string;
  label: string;
  detail?: string;
  meta?: string;
  count?: number;
  checked: boolean;
};

/**
 * "Who responds?" of the follow-up (PRD §10.11), as the admin console draws it: the criterion as a segmented control
 * (everybody, people, area, role), its checkbox list and how many people it reaches.
 */
export function AudienceSelector({
  members,
  value,
  onChange,
}: {
  members: Member[];
  value: Audience;
  onChange: (audience: Audience) => void;
}) {
  const {t} = useTranslation('pages.project-new');
  const labelId = useId();
  const [search, setSearch] = useState('');
  const count = audienceMembers(value, members).length;
  const isChecked = (item: string) =>
    value.values.some((v) => fold(v) === fold(item));

  const choices: Choice[] =
    value.type === 'members'
      ? [...members]
          .sort((a, b) => a.name.localeCompare(b.name))
          .filter((m) =>
            matchesAllWords(
              [m.name, m.email, m.phone, m.area, m.role]
                .filter(Boolean)
                .join(' '),
              search,
            ),
          )
          .map((m) => ({
            value: m.organization_user_id,
            label: m.name,
            detail: m.email || m.phone || '',
            meta: [m.area, m.role].filter(Boolean).join(' · '),
            checked: isChecked(m.organization_user_id),
          }))
      : value.type === 'area' || value.type === 'role'
        ? distinctValues(members, value.type).map((item) => ({
            value: item.value,
            label: item.value,
            count: item.count,
            checked: isChecked(item.value),
          }))
        : [];
  const missingData =
    (value.type === 'area' || value.type === 'role') &&
    distinctValues(members, value.type).length === 0;
  const noMatches =
    value.type !== 'all' && !missingData && choices.length === 0;

  const selectMode = (type: Audience['type']) => {
    if (type !== value.type) {
      setSearch('');
      onChange({type, values: []});
    }
  };
  const toggle = (item: string) =>
    onChange({
      type: value.type,
      values: isChecked(item)
        ? value.values.filter((v) => fold(v) !== fold(item))
        : [...value.values, item],
    });

  return (
    <div className="prj-aud">
      <div className="prj-aud__head">
        <span id={labelId} className="prj-new__label">
          {t('audience.label')}
        </span>
        <p className="prj-new__small-muted">{t('audience.hint')}</p>
      </div>

      <div
        className="prj-aud__modes"
        role="radiogroup"
        aria-labelledby={labelId}
      >
        {TYPES.map((type) => (
          <button
            key={type}
            type="button"
            role="radio"
            aria-checked={value.type === type}
            className={joinClasses(
              'prj-aud__mode',
              value.type === type && 'prj-aud__mode--selected',
            )}
            onClick={() => selectMode(type)}
          >
            {t(`audience.${type}`)}
          </button>
        ))}
      </div>

      {value.type !== 'all' ? (
        <div className="prj-aud__panel">
          {value.type === 'members' ? (
            <div className="prj-aud__search">
              <Icon name="search" size={16} />
              <input
                className="prj-aud__search-input"
                type="search"
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder={t('audience.search')}
                aria-label={t('audience.search')}
                autoComplete="off"
              />
            </div>
          ) : null}
          {missingData ? (
            <p className="prj-aud__note">
              <Icon name="info" size={16} />
              {t(
                value.type === 'area'
                  ? 'audience.missingArea'
                  : 'audience.missingRole',
              )}
            </p>
          ) : null}
          {noMatches ? (
            <p className="prj-aud__note prj-aud__note--muted">
              {t('audience.noMatches')}
            </p>
          ) : null}
          <ul className="prj-aud__list">
            {choices.map((choice) => (
              <li key={choice.value}>
                <label className="prj-aud__choice">
                  <input
                    type="checkbox"
                    className="prj-aud__check"
                    checked={choice.checked}
                    onChange={() => toggle(choice.value)}
                  />
                  <span className="prj-aud__choice-text">
                    <span className="prj-aud__choice-label">
                      {choice.label}
                    </span>
                    {choice.detail ? (
                      <span className="prj-aud__choice-detail">
                        {choice.detail}
                      </span>
                    ) : null}
                  </span>
                  {choice.meta ? (
                    <span className="prj-aud__choice-meta">{choice.meta}</span>
                  ) : null}
                  {choice.count !== undefined ? (
                    <span className="prj-aud__choice-count">
                      {t('audience.people', {count: choice.count})}
                    </span>
                  ) : null}
                </label>
              </li>
            ))}
          </ul>
        </div>
      ) : null}

      <p className="prj-aud__count" aria-live="polite">
        <Icon name="users" size={16} />
        {t('audience.respondents', {count})}
      </p>
    </div>
  );
}
