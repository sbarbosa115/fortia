import {emptyMember, type MemberDraft} from '@console/entities/organization';
import {Button, Field, Icon, IconButton, Modal, TextInput} from '@shared/ui';
import {type FormEvent, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {
  type NewOrganization,
  newOrganizationErrors,
  newOrganizationValid,
} from '../model/wizard';

type MemberField = 'name' | 'email' | 'phone' | 'area' | 'role';
const FIELDS: MemberField[] = ['name', 'email', 'phone', 'area', 'role'];
const TYPE: Record<MemberField, string> = {
  name: 'text',
  email: 'email',
  phone: 'tel',
  area: 'text',
  role: 'text',
};

/**
 * A new organization for the wizard (PRD §10.12 step 2): name, email domain and members, with the organization
 * form's rules (a name; each member a name and an email or phone, no repeats). It is kept in the wizard and saved
 * on Create.
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
  const {t} = useTranslation('pages.project-new');
  const {t: tEntity} = useTranslation('entities.organization');
  const [value, setValue] = useState<NewOrganization>(
    () => initial ?? {name: initialName, domain: '', members: [emptyMember()]},
  );
  const [submitted, setSubmitted] = useState(false);
  const errors = newOrganizationErrors(value);

  const changeMember = (key: string, change: Partial<MemberDraft>) =>
    setValue((current) => ({
      ...current,
      members: current.members.map((m) =>
        m.key === key ? {...m, ...change} : m,
      ),
    }));
  const onSubmit = (event: FormEvent) => {
    event.preventDefault();
    setSubmitted(true);
    if (newOrganizationValid(value)) {
      onSave(value);
    }
  };

  return (
    <Modal
      open
      wide
      title={t('organizationDialog.title')}
      onClose={onClose}
      footer={
        <>
          <Button onClick={onClose}>
            {t('actions.cancel', {ns: 'shared'})}
          </Button>
          <Button
            variant="primary"
            type="submit"
            form="prj-new-organization-form"
          >
            {t('organizationDialog.save')}
          </Button>
        </>
      }
    >
      <form
        id="prj-new-organization-form"
        className="prj-new__stack"
        noValidate
        onSubmit={onSubmit}
      >
        <p className="muted">{t('organizationDialog.hint')}</p>
        <Field
          label={t('organizationDialog.organizationName')}
          required
          error={
            submitted && errors.name ? tEntity(`errors.${errors.name}`) : null
          }
        >
          <TextInput
            value={value.name}
            maxLength={200}
            onChange={(event) => setValue({...value, name: event.target.value})}
          />
        </Field>
        <Field
          label={t('organizationDialog.domain')}
          hint={t('organizationDialog.domainHint')}
        >
          <TextInput
            value={value.domain}
            maxLength={255}
            placeholder="acme.com"
            onChange={(event) =>
              setValue({...value, domain: event.target.value})
            }
          />
        </Field>
        <fieldset className="prj-new__fieldset">
          <legend className="field__label">
            {t('organizationDialog.members')}
          </legend>
          <p className="field__hint">{t('organizationDialog.membersHint')}</p>
          <ol className="prj-new__members">
            {value.members.map((member, index) => {
              const rowErrors = errors.members[index];
              const shown = rowErrors
                ? Object.entries(rowErrors)
                    .filter(([kind]) => submitted || kind === 'duplicate')
                    .map(([, key]) => tEntity(`errors.${key}`))
                : [];
              return (
                <li key={member.key} className="prj-new__member">
                  <div className="prj-new__member-cells">
                    {FIELDS.map((field) => (
                      <TextInput
                        key={field}
                        type={TYPE[field]}
                        value={member[field]}
                        placeholder={t(`organizationDialog.${field}`)}
                        aria-label={t('organizationDialog.memberField', {
                          field: t(`organizationDialog.${field}`),
                          position: index + 1,
                        })}
                        onChange={(event) =>
                          changeMember(member.key, {
                            [field]: event.target.value,
                          })
                        }
                      />
                    ))}
                    <IconButton
                      label={t('organizationDialog.removeMember', {
                        position: index + 1,
                      })}
                      icon={<Icon name="trash" size={16} />}
                      variant="ghost"
                      onClick={() =>
                        setValue({
                          ...value,
                          members: value.members.filter(
                            (m) => m.key !== member.key,
                          ),
                        })
                      }
                    />
                  </div>
                  {shown.length > 0 ? (
                    <span className="field__error" role="alert">
                      {shown.join(' · ')}
                    </span>
                  ) : null}
                </li>
              );
            })}
          </ol>
          <Button
            size="sm"
            icon={<Icon name="plus" size={14} />}
            onClick={() =>
              setValue({...value, members: [...value.members, emptyMember()]})
            }
          >
            {t('organizationDialog.addMember')}
          </Button>
        </fieldset>
      </form>
    </Modal>
  );
}
