import {useViewer} from '@console/entities/viewer';
import {PasswordStrength} from '@console/features/password-strength';
import {api, type Schema} from '@shared/api';
import {useDocumentTitle} from '@shared/lib';
import {
  Button,
  Card,
  ChoiceCards,
  EmptyState,
  Field,
  PageHeader,
  TextInput,
  useToast,
} from '@shared/ui';
import {useQueryClient} from '@tanstack/react-query';
import {type FormEvent, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {Link, useNavigate} from 'react-router';
import {
  createErrorKey,
  type NewUserErrors,
  type NewUserRole,
  PERMISSIONS,
  validateNewUser,
} from '../model/newUser';
import './user-new.css';

/**
 * /users/new (PRD §10.16): full name, email, password (with the strength meter) and the role as cards — Read only by
 * default — with the permission matrix of §4.2. "User {{name}} created" and back to /users.
 */
export function UserNewPage() {
  const {t} = useTranslation('pages.user-new');
  const {t: ts} = useTranslation('shared');
  const viewer = useViewer();
  const toast = useToast();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [role, setRole] = useState<NewUserRole>('Customer-Read-Only');
  const [errors, setErrors] = useState<NewUserErrors>({});
  const [failure, setFailure] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  useDocumentTitle(`Mappi - ${t('title')}`);

  if (!viewer.canWrite) {
    return (
      <EmptyState
        title={t('adminsOnly')}
        action={<Link to="/users">{t('back')}</Link>}
      />
    );
  }

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    const found = validateNewUser({name, email, password});
    setErrors(found);
    setFailure(null);
    if (Object.keys(found).length > 0) {
      return;
    }
    setBusy(true);
    try {
      const user = await api.post<Schema<'UserOutput'>>('/users', {
        name: name.trim(),
        email: email.trim().toLowerCase(),
        password,
        role,
      });
      void queryClient.invalidateQueries({queryKey: ['users']});
      toast.success(t('created', {name: user.name}));
      navigate('/users');
    } catch (error) {
      setFailure(t(createErrorKey(error)));
      setBusy(false);
    }
  };

  return (
    <div className="user-new">
      <PageHeader title={t('title')} subtitle={t('subtitle')} />
      <Card className="user-new__card">
        <form className="stack" onSubmit={submit} noValidate>
          <div className="grid-2">
            <Field
              label={t('fields.name')}
              error={errors.name ? t(errors.name) : null}
              required
            >
              <TextInput
                autoComplete="off"
                maxLength={50}
                value={name}
                onChange={(e) => setName(e.target.value)}
              />
            </Field>
            <Field
              label={t('fields.email')}
              error={errors.email ? t(errors.email) : null}
              required
            >
              <TextInput
                type="email"
                autoComplete="off"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
              />
            </Field>
          </div>
          <Field
            label={t('fields.password')}
            hint={t('passwordHint')}
            error={errors.password ? t(errors.password) : null}
            required
          >
            <TextInput
              type="password"
              autoComplete="new-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
            />
          </Field>
          <PasswordStrength password={password} />
          <fieldset className="user-new__roles">
            <legend className="field__label">{t('fields.role')}</legend>
            <ChoiceCards<NewUserRole>
              label={t('fields.role')}
              value={role}
              onChange={setRole}
              choices={[
                {
                  value: 'Customer-Admin',
                  title: t('roles.admin.title'),
                  body: t('roles.admin.body'),
                },
                {
                  value: 'Customer-Read-Only',
                  title: t('roles.readOnly.title'),
                  body: t('roles.readOnly.body'),
                },
              ]}
            />
          </fieldset>
          <table className="table permissions">
            <caption className="permissions__caption">
              {t('matrix.caption')}
            </caption>
            <thead>
              <tr>
                <th scope="col">{t('matrix.action')}</th>
                <th scope="col">{t('roles.admin.title')}</th>
                <th scope="col">{t('roles.readOnly.title')}</th>
              </tr>
            </thead>
            <tbody>
              {PERMISSIONS.map(([action, admin, reader]) => (
                <tr key={action}>
                  <th scope="row">{t(`matrix.actions.${action}`)}</th>
                  {[admin, reader].map((allowed, column) => (
                    <td
                      key={column}
                      className={
                        allowed ? 'permissions__yes' : 'permissions__no'
                      }
                    >
                      <span aria-hidden>
                        {allowed ? t('matrix.yesMark') : t('matrix.noMark')}
                      </span>
                      <span className="visually-hidden">
                        {allowed ? t('matrix.yes') : t('matrix.no')}
                      </span>
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
          {failure ? (
            <p className="field__error" role="alert">
              {failure}
            </p>
          ) : null}
          <div className="row user-new__actions">
            <Button onClick={() => navigate('/users')}>
              {ts('actions.cancel')}
            </Button>
            <Button type="submit" variant="primary" loading={busy}>
              {t('submit')}
            </Button>
          </div>
        </form>
      </Card>
    </div>
  );
}
