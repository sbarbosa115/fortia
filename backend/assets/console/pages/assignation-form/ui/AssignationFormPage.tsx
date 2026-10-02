import {useCopyLink} from '@console/entities/assignation';
import {isApiError} from '@shared/api';
import {joinClasses, useDocumentTitle} from '@shared/lib';
import {
  Button,
  Card,
  CardBody,
  EmptyState,
  ErrorState,
  Field,
  Icon,
  LoadingState,
  PageHeader,
  TextInput,
} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {Link, useParams} from 'react-router';
import {type Step, useAssignationForm} from '../model/useAssignationForm';
import {BasicStep} from './BasicStep';
import {RegistrationStep} from './RegistrationStep';
import './assignation-form.css';

const STEPS: {key: Step; label: string}[] = [
  {key: 'basic', label: 'steps.basic'},
  {key: 'registration', label: 'steps.registration'},
  {key: 'done', label: 'steps.save'},
];

/** /assignations/new and /assignations/:id/edit (PRD §10.11): the wizard Basic → Registration → Save. */
export function AssignationFormPage() {
  const {t} = useTranslation('pages.assignation-form');
  const {t: tShared} = useTranslation('shared');
  const {id} = useParams();
  const state = useAssignationForm(id);
  const copyLink = useCopyLink();
  const title = state.editing ? t('editTitle') : t('newTitle');
  useDocumentTitle(`Mappi - ${title}`);

  if (state.loading) {
    return <LoadingState />;
  }
  if (state.loadError) {
    const notFound =
      isApiError(state.loadError) && state.loadError.status === 404;
    return (
      <Card>
        {notFound ? (
          <EmptyState
            title={t('notFound')}
            action={
              <Link className="btn btn--secondary" to="/assignations">
                {t('backToList')}
              </Link>
            }
          />
        ) : (
          <ErrorState error={state.loadError} onRetry={state.retry} />
        )}
      </Card>
    );
  }

  const current = STEPS.findIndex((step) => step.key === state.step);
  return (
    <div className="asg-form">
      <PageHeader title={title} subtitle={t('subtitle')} />
      <ol className="asg-form__steps" aria-label={t('steps.label')}>
        {STEPS.map((step, index) => (
          <li
            key={step.key}
            className={joinClasses(
              'asg-form__step',
              index === current && 'asg-form__step--current',
              index < current && 'asg-form__step--done',
            )}
            aria-current={index === current ? 'step' : undefined}
          >
            <span className="asg-form__step-number" aria-hidden>
              {index < current ? <Icon name="check" size={14} /> : index + 1}
            </span>
            {t(step.label)}
          </li>
        ))}
      </ol>

      {state.step === 'basic' ? <BasicStep state={state} /> : null}
      {state.step === 'registration' ? (
        <RegistrationStep state={state} />
      ) : null}
      {state.step === 'done' && state.result ? (
        <Card>
          <CardBody>
            <div className="asg-form__done">
              <h2 className="serif-heading">
                {state.editing ? t('done.updated') : t('done.created')}
              </h2>
              <p>{t('done.body', {name: state.result.name})}</p>
              <Field label={t('done.link')}>
                <TextInput readOnly value={state.result.url} />
              </Field>
              <div className="row">
                <Button
                  icon={<Icon name="copy" size={16} />}
                  onClick={() => state.result && copyLink(state.result.url)}
                >
                  {t('done.copy')}
                </Button>
                <Link
                  className="btn btn--primary"
                  to={state.backTo?.to ?? '/assignations'}
                >
                  {state.backTo
                    ? tShared(`back.${state.backTo.kind}`)
                    : t('done.toList')}
                </Link>
              </div>
            </div>
          </CardBody>
        </Card>
      ) : null}

      {state.step === 'done' ? null : (
        <div className="asg-form__actions">
          <Button onClick={state.cancel}>{tShared('actions.cancel')}</Button>
          {state.step === 'registration' ? (
            <Button onClick={state.back}>{t('actions.back')}</Button>
          ) : null}
          {state.step === 'basic' ? (
            <Button variant="primary" onClick={state.next}>
              {t('actions.next')}
            </Button>
          ) : (
            <Button
              variant="primary"
              loading={state.saving}
              icon={<Icon name="check" size={16} />}
              onClick={state.submit}
            >
              {state.editing ? t('actions.save') : t('actions.create')}
            </Button>
          )}
        </div>
      )}
    </div>
  );
}
