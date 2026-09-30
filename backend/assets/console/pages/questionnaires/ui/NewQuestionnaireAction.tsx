import {Button, Icon} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {Link} from 'react-router';

/** "New Questionnaire": a link to /questionnaires/new, disabled with the reason for read-only users. */
export function NewQuestionnaireAction({canWrite}: {canWrite: boolean}) {
  const {t} = useTranslation('pages.questionnaires');
  if (!canWrite) {
    return (
      <Button
        variant="primary"
        icon={<Icon name="plus" />}
        disabledReason={t('readOnly.create', {ns: 'shared'})}
      >
        {t('new')}
      </Button>
    );
  }
  return (
    <Link to="/questionnaires/new" className="btn btn--primary">
      <Icon name="plus" />
      {t('new')}
    </Link>
  );
}
