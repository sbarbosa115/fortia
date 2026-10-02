import {
  type AssignationType,
  AudiencePicker,
} from '@console/entities/assignation';
import {
  Card,
  CardBody,
  ChoiceCards,
  Field,
  TextArea,
  TextInput,
} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import type {AssignationFormState} from '../model/useAssignationForm';
import {OrganizationPicker} from './OrganizationPicker';
import {QuestionnairePicker} from './QuestionnairePicker';

const TYPES: AssignationType[] = ['default', 'follow_up'];

/** The Basic step (PRD §10.11): type (only when creating), due date, organization, audience, questionnaire, name. */
export function BasicStep({state}: {state: AssignationFormState}) {
  const {t} = useTranslation('pages.assignation-form');
  const {form} = state;
  const error = (field: keyof typeof state.errors) => {
    const key = state.errors[field];
    return state.showErrors && key ? t(key) : null;
  };

  return (
    <Card>
      <CardBody>
        <div className="asg-form__grid">
          {state.editing ? null : (
            <ChoiceCards
              label={t('type.label')}
              value={form.type}
              onChange={(type) => state.setField({type})}
              choices={TYPES.map((type) => ({
                value: type,
                title: t(`type.${type}`),
                body: t(
                  type === 'default' ? 'type.defaultBody' : 'type.followUpBody',
                ),
              }))}
            />
          )}
          {form.type === 'follow_up' ? (
            <Field
              label={t('dueDate.label')}
              hint={t('dueDate.hint')}
              error={error('dueDate')}
            >
              <TextInput
                type="date"
                value={form.dueDate}
                onChange={(event) =>
                  state.setField({dueDate: event.target.value})
                }
              />
            </Field>
          ) : null}
          <OrganizationPicker
            organizations={state.organizations}
            value={form.organizationId}
            onChange={state.setOrganization}
            error={error('organization')}
            lockedReason={state.inProject ? t('organization.inProject') : null}
          />
          <AudiencePicker
            members={state.organization?.organization_users ?? null}
            value={form.audience}
            onChange={(audience) => state.setField({audience})}
            error={error('audience')}
          />
          <QuestionnairePicker
            value={form.questionnaire}
            onChange={(questionnaire) => state.setField({questionnaire})}
            error={error('questionnaire')}
            conflict={state.conflict}
          />
          <Field label={t('name.label')} required error={error('name')}>
            <TextInput
              value={form.name}
              maxLength={200}
              onChange={(event) => state.setField({name: event.target.value})}
            />
          </Field>
          <Field label={t('description.label')} hint={t('description.hint')}>
            <TextArea
              value={form.description}
              maxLength={2000}
              onChange={(event) =>
                state.setField({description: event.target.value})
              }
            />
          </Field>
        </div>
      </CardBody>
    </Card>
  );
}
