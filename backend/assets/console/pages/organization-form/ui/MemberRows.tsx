import type {MemberDraft, MemberErrors} from '@console/entities/organization';
import {EmptyState, IconButton, Icon, TextInput} from '@shared/ui';
import {useTranslation} from 'react-i18next';

type Column = 'name' | 'email' | 'phone' | 'role' | 'area';

const COLUMNS: Column[] = ['name', 'email', 'phone', 'role', 'area'];
const MAX_LENGTH: Record<Column, number> = {
  name: 200,
  email: 255,
  phone: 50,
  role: 120,
  area: 120,
};
const INPUT_TYPE: Record<Column, string> = {
  name: 'text',
  email: 'email',
  phone: 'tel',
  role: 'text',
  area: 'text',
};

/**
 * The member list edited inline (PRD §10.10): one row per member with name, email, phone, role and area, and a
 * remove button. Each row shows its problems under it: its own rules once the user tried to save, "already in the
 * list" at once.
 */
export function MemberRows({
  members,
  errors,
  showErrors,
  onChange,
  onBlur,
  onRemove,
}: {
  members: MemberDraft[];
  errors: Array<MemberErrors | undefined>;
  showErrors: boolean;
  onChange: (key: string, change: Partial<Record<Column, string>>) => void;
  onBlur: (key: string) => void;
  onRemove: (key: string) => void;
}) {
  const {t} = useTranslation('pages.organization-form');
  const {t: tEntity} = useTranslation('entities.organization');
  if (members.length === 0) {
    return <EmptyState title={t('members.empty')} body={t('members.emptyBody')} />;
  }
  return (
    <div className="org-members">
      <div className="org-members__head" aria-hidden>
        {COLUMNS.map((column) => (
          <span key={column}>
            {t(`members.${column}`)}
            {column === 'name' ? <span className="field__required">*</span> : null}
          </span>
        ))}
        <span />
      </div>
      <ol className="org-members__list">
        {members.map((member, index) => {
          const position = index + 1;
          const rowErrors = errors[index];
          const visible = rowErrors
            ? (Object.entries(rowErrors) as Array<[keyof MemberErrors, string]>)
                .filter(([kind]) => showErrors || kind === 'duplicate')
                .map(([, key]) => tEntity(`errors.${key}`))
            : [];
          const errorId = `member-${member.key}-errors`;
          return (
            <li key={member.key} className="org-members__row">
              <div className="org-members__cells">
                {COLUMNS.map((column) => (
                  <TextInput
                    key={column}
                    type={INPUT_TYPE[column]}
                    value={member[column]}
                    maxLength={MAX_LENGTH[column]}
                    aria-label={t('members.cellLabel', {
                      field: t(`members.${column}`),
                      position,
                    })}
                    aria-invalid={
                      visible.length > 0 && invalidColumn(rowErrors, column, showErrors)
                        ? true
                        : undefined
                    }
                    aria-describedby={visible.length > 0 ? errorId : undefined}
                    required={column === 'name'}
                    autoComplete="off"
                    placeholder={t(`members.${column}Placeholder`)}
                    onChange={(event) => onChange(member.key, {[column]: event.target.value})}
                    onBlur={() => onBlur(member.key)}
                  />
                ))}
                <IconButton
                  label={t('members.remove', {position})}
                  icon={<Icon name="trash" size={16} />}
                  variant="ghost"
                  onClick={() => onRemove(member.key)}
                />
              </div>
              {visible.length > 0 ? (
                <ul id={errorId} className="org-members__errors" role="alert">
                  {visible.map((message) => (
                    <li key={message}>{message}</li>
                  ))}
                </ul>
              ) : null}
            </li>
          );
        })}
      </ol>
    </div>
  );
}

function invalidColumn(
  errors: MemberErrors | undefined,
  column: Column,
  showErrors: boolean,
): boolean {
  if (!errors) {
    return false;
  }
  if (errors.duplicate && (column === 'email' || column === 'phone')) {
    return true;
  }
  if (!showErrors) {
    return false;
  }
  return (
    (column === 'name' && Boolean(errors.name)) ||
    (column === 'email' && Boolean(errors.email || errors.contact)) ||
    (column === 'phone' && Boolean(errors.contact))
  );
}
