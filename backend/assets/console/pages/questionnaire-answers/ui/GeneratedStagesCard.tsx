import type {AnswersPage} from '@console/entities/answer';
import {formatDateTime} from '@shared/lib';
import {Card, CardBody, CardHeader} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {Link} from 'react-router';

/** The generated child stages of a chain (PRD §10.8 "Generated questionnaires"). */
export function GeneratedStagesCard({
  stages,
}: {
  stages: AnswersPage['generated_stages'];
}) {
  const {t, i18n} = useTranslation('pages.questionnaire-answers');
  if (stages.length === 0) {
    return null;
  }
  return (
    <Card>
      <CardHeader title={t('generated.title')} />
      <CardBody>
        <p className="muted">{t('generated.body')}</p>
        <ul className="answers-stages">
          {stages.map((stage) => (
            <li key={stage.questionnaire_id}>
              <Link to={`/questionnaires/${stage.questionnaire_id}/answers`}>
                {stage.title}
              </Link>
              <span className="muted">
                {formatDateTime(stage.created_at, i18n.language)}
              </span>
            </li>
          ))}
        </ul>
      </CardBody>
    </Card>
  );
}
