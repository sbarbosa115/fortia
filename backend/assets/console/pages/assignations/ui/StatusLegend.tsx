import {useTranslation} from 'react-i18next';
import {LEGEND_DETOURS, LEGEND_STEPS} from '../model/rows';
import {ProjectStatusPill} from './ProjectStatusPill';

/** How an assignation moves through the statuses the list uses: the numbered path, then the two detours. */
export function StatusLegend() {
  const {t} = useTranslation('pages.assignations');
  return (
    <section aria-labelledby="projects-legend" className="projects-legend">
      <div className="projects-legend__head">
        <h2 id="projects-legend" className="projects-legend__title">
          {t('legend.title')}
        </h2>
        <p className="projects-legend__hint">{t('legend.hint')}</p>
      </div>
      <ol className="projects-legend__steps">
        {LEGEND_STEPS.map((status, index) => (
          <li
            key={status}
            className="projects-legend__step"
            data-you={status === 'review' || undefined}
          >
            <span className="projects-legend__step-head">
              <span className="projects-legend__number" aria-hidden="true">
                {index + 1}
              </span>
              <ProjectStatusPill status={status} />
            </span>
            {status === 'review' ? (
              <span>
                <strong>{t('legend.reviewYou')}</strong> {t('legend.review')}
              </span>
            ) : (
              <span>{t(`legend.${status}`)}</span>
            )}
          </li>
        ))}
      </ol>
      <ul className="projects-legend__detours">
        {LEGEND_DETOURS.map((status) => (
          <li key={status}>
            <ProjectStatusPill status={status} />
            <span>{t(`legend.${status}`)}</span>
          </li>
        ))}
      </ul>
    </section>
  );
}
