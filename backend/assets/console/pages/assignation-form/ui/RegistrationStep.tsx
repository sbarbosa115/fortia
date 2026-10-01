import {
  REGISTRATION_FIELDS,
  type RegistrationField,
} from '@console/entities/assignation';
import {Card, CardBody, CardHeader} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import type {AssignationFormState} from '../model/useAssignationForm';

/**
 * The Registration step (PRD §10.11): which fields the respondents' sign-in slide shows and requires. Full name is
 * fixed; email or phone must be visible and required.
 */
export function RegistrationStep({state}: {state: AssignationFormState}) {
  const {t} = useTranslation('pages.assignation-form');
  const settings = state.form.registration;
  const set = (
    field: RegistrationField,
    change: Partial<{visible: boolean; required: boolean}>,
  ) => {
    const next = {...settings[field], ...change};
    // Requiring a field shows it; hiding a field stops requiring it.
    if (change.required) {
      next.visible = true;
    }
    if (change.visible === false) {
      next.required = false;
    }
    state.setField({registration: {...settings, [field]: next}});
  };

  return (
    <Card>
      <CardHeader title={t('registration.title')} />
      <CardBody>
        <p className="muted">{t('registration.intro')}</p>
        <div className="table-wrap">
          <table className="table">
            <caption className="visually-hidden">
              {t('registration.title')}
            </caption>
            <thead>
              <tr>
                <th scope="col">{t('registration.field')}</th>
                <th scope="col">{t('registration.visible')}</th>
                <th scope="col">{t('registration.required')}</th>
              </tr>
            </thead>
            <tbody>
              {REGISTRATION_FIELDS.map((field) => {
                const label = t(`registration.fields.${field}`);
                const fixed = field === 'name';
                return (
                  <tr key={field}>
                    <th scope="row">
                      {label}
                      {fixed ? (
                        <span className="muted">
                          {' '}
                          · {t('registration.fixed')}
                        </span>
                      ) : null}
                    </th>
                    <td>
                      <input
                        type="checkbox"
                        aria-label={t('registration.visibleLabel', {
                          field: label,
                        })}
                        checked={settings[field].visible}
                        disabled={fixed}
                        onChange={(event) =>
                          set(field, {visible: event.target.checked})
                        }
                      />
                    </td>
                    <td>
                      <input
                        type="checkbox"
                        aria-label={t('registration.requiredLabel', {
                          field: label,
                        })}
                        checked={settings[field].required}
                        disabled={fixed}
                        onChange={(event) =>
                          set(field, {required: event.target.checked})
                        }
                      />
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
        {state.registrationProblem ? (
          <p className="field__error" role="alert">
            {t(state.registrationProblem)}
          </p>
        ) : null}
      </CardBody>
    </Card>
  );
}
