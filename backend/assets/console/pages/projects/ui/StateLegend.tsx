import {PROJECT_STATES} from '@console/entities/project';
import {Card, CardBody} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {StateBadge} from './StateBadge';

/** What each state means (PRD §10.12 "State legend"). */
export function StateLegend() {
  const {t} = useTranslation('pages.projects');
  return (
    <Card>
      <CardBody>
        <h2 className="projects__legend-title">{t('legend.title')}</h2>
        <dl className="projects__legend">
          {PROJECT_STATES.map((state) => (
            <div key={state} className="projects__legend-item">
              <dt>
                <StateBadge state={state} />
              </dt>
              <dd>{t(`legend.${state}`)}</dd>
            </div>
          ))}
        </dl>
      </CardBody>
    </Card>
  );
}
