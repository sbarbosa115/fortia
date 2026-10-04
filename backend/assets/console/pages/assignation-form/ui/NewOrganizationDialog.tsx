import {
  domainMismatches,
  emptyMember,
  type MemberDraft,
  memberErrors,
} from '@console/entities/organization';
import {ImportMembersCsv} from '@console/features/import-members-csv';
import {Icon, Modal, Toggle} from '@shared/ui';
import {type FormEvent, type KeyboardEvent, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {
  type NewOrganization,
  newOrganizationErrors,
  newOrganizationValid,
} from '../model/wizard';

type MemberField = 'name' | 'email' | 'phone' | 'role' | 'area';
const FIELDS: MemberField[] = ['name', 'email', 'phone', 'role', 'area'];
const LABEL: Record<MemberField, string> = {
  name: 'memberName',
  email: 'memberEmail',
  phone: 'memberPhone',
  role: 'memberRole',
  area: 'memberArea',
};

/** The first problem of the last row of `members` (the one being added or edited), as an i18n key. */
function rowError(members: MemberDraft[], index: number): string | null {
  const errors = memberErrors(members)[index];
  if (!errors) return null;
  return (
    errors.name ?? errors.email ?? errors.contact ?? errors.duplicate ?? null
  );
}

/**
 * A new organization for the wizard (PRD §10.12 step 2), as the admin console's organization form: name, email
 * domain, description, active, and its members — added one at a time (or from a CSV), each with a name and an email
 * or a phone, no repeats. It is kept in the wizard and saved on Create.
 */
export function NewOrganizationDialog({
  initial,
  initialName,
  onSave,
  onClose,
}: {
  initial: NewOrganization | null;
  initialName: string;
  onSave: (organization: NewOrganization) => void;
  onClose: () => void;
}) {
  const {t} = useTranslation('pages.assignation-form');
  const {t: tEntity} = useTranslation('entities.organization');
  const [value, setValue] = useState<NewOrganization>(
    () =>
      initial ?? {
        name: initialName,
        domain: '',
        description: '',
        active: true,
        members: [],
      },
  );
  const [submitted, setSubmitted] = useState(false);
  const [draft, setDraft] = useState<MemberDraft>(emptyMember);
  const [addError, setAddError] = useState<string | null>(null);
  const [editing, setEditing] = useState<MemberDraft | null>(null);
  const [editError, setEditError] = useState<string | null>(null);
  const errors = newOrganizationErrors(value);
  const offDomain = domainMismatches(value.members, value.domain);

  const addMember = () => {
    const next = [...value.members, draft];
    const problem = rowError(next, next.length - 1);
    if (problem) {
      setAddError(tEntity(`errors.${problem}`));
      return;
    }
    setValue({...value, members: next});
    setDraft(emptyMember());
    setAddError(null);
  };
  const saveEdit = () => {
    if (!editing) return;
    // Checked against the others, as the last row, so a repeat of any of them is caught.
    const others = value.members.filter((m) => m.key !== editing.key);
    const problem = rowError([...others, editing], others.length);
    if (problem) {
      setEditError(tEntity(`errors.${problem}`));
      return;
    }
    setValue({
      ...value,
      members: value.members.map((m) => (m.key === editing.key ? editing : m)),
    });
    setEditing(null);
    setEditError(null);
  };
  const onEnter =
    (action: () => void) => (event: KeyboardEvent<HTMLInputElement>) => {
      if (event.key === 'Enter') {
        event.preventDefault();
        action();
      }
    };
  const onSubmit = (event: FormEvent) => {
    event.preventDefault();
    setSubmitted(true);
    if (newOrganizationValid(value)) {
      onSave(value);
    }
  };
  const membersError = submitted
    ? errors.members
        .map(
          (row) =>
            row && (row.name ?? row.email ?? row.contact ?? row.duplicate),
        )
        .find(Boolean)
    : undefined;

  return (
    <Modal
      open
      wide
      title={t('organizationDialog.title')}
      onClose={onClose}
      footer={
        <>
          <button
            type="button"
            className="asg-wiz__btn asg-wiz__btn--outline"
            onClick={onClose}
          >
            {t('actions.cancel', {ns: 'shared'})}
          </button>
          <button
            type="submit"
            form="asg-wiz-organization-form"
            className="asg-wiz__btn asg-wiz__btn--primary"
          >
            {t('organizationDialog.save')}
          </button>
        </>
      }
    >
      <form
        id="asg-wiz-organization-form"
        className="asg-dlg asg-dlg--organization"
        noValidate
        onSubmit={onSubmit}
      >
        <p className="asg-dlg__description">
          {t('organizationDialog.description')}
        </p>

        <div className="asg-dlg__field">
          <label htmlFor="asg-org-name" className="asg-wiz__label">
            {t('organizationDialog.name')} *
          </label>
          <input
            id="asg-org-name"
            className="asg-wiz__input"
            value={value.name}
            maxLength={200}
            placeholder={t('organizationDialog.namePlaceholder')}
            aria-required="true"
            aria-invalid={submitted && errors.name ? true : undefined}
            onChange={(event) => setValue({...value, name: event.target.value})}
          />
        </div>

        <div className="asg-dlg__field">
          <label htmlFor="asg-org-domain" className="asg-wiz__label">
            {t('organizationDialog.domain')}
          </label>
          <input
            id="asg-org-domain"
            className="asg-wiz__input"
            value={value.domain}
            maxLength={255}
            placeholder={t('organizationDialog.domainPlaceholder')}
            onChange={(event) =>
              setValue({...value, domain: event.target.value})
            }
          />
        </div>

        <div className="asg-dlg__field">
          <label htmlFor="asg-org-description" className="asg-wiz__label">
            {t('organizationDialog.orgDescription')}
          </label>
          <textarea
            id="asg-org-description"
            className="asg-wiz__input asg-wiz__textarea"
            rows={3}
            value={value.description ?? ''}
            placeholder={t('organizationDialog.orgDescriptionPlaceholder')}
            onChange={(event) =>
              setValue({...value, description: event.target.value})
            }
          />
        </div>

        <div className="asg-dlg__switch">
          <span className="asg-wiz__label">
            {t('organizationDialog.active')}
          </span>
          <Toggle
            label={t('organizationDialog.active')}
            hideLabel
            checked={value.active ?? true}
            onChange={(active) => setValue({...value, active})}
          />
        </div>

        <fieldset className="asg-dlg__members">
          <legend className="asg-wiz__label asg-dlg__members-legend">
            {t('organizationDialog.members')}
          </legend>
          <p className="asg-wiz__small-muted">
            {t('organizationDialog.membersHint')}
          </p>
          <ImportMembersCsv
            existing={value.members}
            onImport={(imported) =>
              setValue({...value, members: [...value.members, ...imported]})
            }
          />

          <div className="asg-dlg__member-grid">
            {FIELDS.map((field) => (
              <div key={field} className="asg-dlg__member-field">
                <label
                  htmlFor={`asg-org-member-${field}`}
                  className="asg-dlg__xs-label"
                >
                  {t(`organizationDialog.${LABEL[field]}`)}
                  {field === 'name' ? ' *' : ''}
                </label>
                <input
                  id={`asg-org-member-${field}`}
                  className="asg-wiz__input"
                  type={
                    field === 'email'
                      ? 'email'
                      : field === 'phone'
                        ? 'tel'
                        : 'text'
                  }
                  value={draft[field]}
                  placeholder={t(
                    `organizationDialog.${LABEL[field]}Placeholder`,
                  )}
                  onChange={(event) =>
                    setDraft({...draft, [field]: event.target.value})
                  }
                  onKeyDown={onEnter(addMember)}
                />
              </div>
            ))}
            <button
              type="button"
              className="asg-dlg__add-member"
              onClick={addMember}
            >
              <Icon name="user-plus" size={16} />
              {t('organizationDialog.addMember')}
            </button>
          </div>
          {addError ? (
            <p className="asg-dlg__error" role="alert">
              {addError}
            </p>
          ) : null}

          <p className="asg-wiz__small-muted asg-dlg__pull-up">
            {tEntity('errors.contactRequired')}
          </p>

          {value.members.length > 0 ? (
            <ul className="asg-dlg__member-list">
              {value.members.map((member) =>
                editing?.key === member.key ? (
                  <li
                    key={member.key}
                    className="asg-dlg__member asg-dlg__member--editing"
                  >
                    <div className="asg-dlg__member-grid">
                      {FIELDS.map((field) => (
                        <input
                          key={field}
                          className="asg-wiz__input"
                          value={editing[field]}
                          placeholder={`${t(`organizationDialog.${LABEL[field]}`)}${field === 'name' ? ' *' : ''}`}
                          aria-label={t(`organizationDialog.${LABEL[field]}`)}
                          onChange={(event) =>
                            setEditing({
                              ...editing,
                              [field]: event.target.value,
                            })
                          }
                          onKeyDown={onEnter(saveEdit)}
                        />
                      ))}
                    </div>
                    {editError ? (
                      <p className="asg-dlg__error" role="alert">
                        {editError}
                      </p>
                    ) : null}
                    <div className="asg-dlg__member-actions">
                      <button
                        type="button"
                        className="asg-dlg__small-button"
                        onClick={() => {
                          setEditing(null);
                          setEditError(null);
                        }}
                      >
                        <Icon name="close" size={14} />
                        {t('organizationDialog.cancelEdit')}
                      </button>
                      <button
                        type="button"
                        className="asg-dlg__small-button asg-dlg__small-button--strong"
                        onClick={saveEdit}
                      >
                        <Icon name="check" size={14} />
                        {t('organizationDialog.saveMember')}
                      </button>
                    </div>
                  </li>
                ) : (
                  <li key={member.key} className="asg-dlg__member">
                    <div className="asg-dlg__member-text">
                      <span className="asg-dlg__member-name">
                        {member.name}
                      </span>
                      <span className="asg-dlg__member-meta">
                        {[member.email, member.phone, member.role, member.area]
                          .filter(Boolean)
                          .map((item) => (
                            <span key={item}>{item}</span>
                          ))}
                      </span>
                    </div>
                    <div className="asg-dlg__member-icons">
                      <button
                        type="button"
                        className="asg-dlg__icon-button"
                        aria-label={t('organizationDialog.editMember', {
                          name: member.name,
                        })}
                        onClick={() => {
                          setEditing(member);
                          setEditError(null);
                        }}
                      >
                        <Icon name="edit" size={16} />
                      </button>
                      <button
                        type="button"
                        className="asg-dlg__icon-button asg-dlg__icon-button--danger"
                        aria-label={t('organizationDialog.removeMember', {
                          name: member.name,
                        })}
                        onClick={() => {
                          if (editing?.key === member.key) setEditing(null);
                          setValue({
                            ...value,
                            members: value.members.filter(
                              (m) => m.key !== member.key,
                            ),
                          });
                        }}
                      >
                        <Icon name="close" size={16} />
                      </button>
                    </div>
                  </li>
                ),
              )}
            </ul>
          ) : (
            <p className="asg-dlg__no-members">
              {t('organizationDialog.noMembers')}
            </p>
          )}

          {offDomain > 0 ? (
            <p className="asg-dlg__domain-warning">
              <Icon name="alert" size={14} />
              {t('organizationDialog.domainWarning', {
                count: offDomain,
                domain: value.domain.trim().toLowerCase(),
              })}
            </p>
          ) : null}
        </fieldset>

        {submitted && (errors.name || membersError) ? (
          <p role="alert" className="asg-dlg__error">
            {errors.name
              ? tEntity(`errors.${errors.name}`)
              : tEntity(`errors.${membersError}`)}
          </p>
        ) : null}
      </form>
    </Modal>
  );
}
