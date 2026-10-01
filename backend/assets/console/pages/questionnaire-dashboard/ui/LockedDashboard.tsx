import {Card, CardBody, Icon} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {Link} from 'react-router';
import {SERIES, SINGLE} from './charts/palette';

const SAMPLE_BARS = [72, 54, 38, 26, 18];
const SAMPLE_SLICES = [45, 30, 25];
const SAMPLE_GRADIENT = SAMPLE_SLICES.map((share, i) => {
  const start = SAMPLE_SLICES.slice(0, i).reduce((sum, s) => sum + s, 0);
  return `${SERIES[i] ?? SINGLE} ${start}% ${start + share}%`;
}).join(', ');

/**
 * Without the "dashboards" capacity (PRD §10.9): a blurred sample dashboard, the plan text and "Get your dashboards"
 * → /profile/plans. The sample is decorative (aria-hidden).
 */
export function LockedDashboard({reason}: {reason: string}) {
  const {t} = useTranslation('pages.questionnaire-dashboard');
  const {t: ts} = useTranslation('shared');
  return (
    <div className="dash-locked">
      <div className="dash-locked__sample" aria-hidden>
        <Card className="dash-locked__chart">
          <div className="dash-locked__bars">
            {SAMPLE_BARS.map((height, i) => (
              <span
                key={i}
                style={{height: `${height}%`, background: SINGLE}}
              />
            ))}
          </div>
        </Card>
        <Card className="dash-locked__chart">
          <div
            className="dash-locked__donut"
            style={{background: `conic-gradient(${SAMPLE_GRADIENT})`}}
          />
        </Card>
        <Card className="dash-locked__chart">
          <div className="dash-locked__bars">
            {[...SAMPLE_BARS].reverse().map((height, i) => (
              <span
                key={i}
                style={{height: `${height}%`, background: SERIES[0]}}
              />
            ))}
          </div>
        </Card>
      </div>
      <Card className="dash-locked__overlay">
        <CardBody>
          <Icon name="lock" />
          <h2 className="dash-locked__title">{t('locked.title')}</h2>
          <p>{ts(`planLimit.${reason}`, {defaultValue: t('locked.body')})}</p>
          <p className="muted">{t('locked.body')}</p>
          <Link className="btn btn--primary" to="/profile/plans">
            {t('locked.cta')}
          </Link>
        </CardBody>
      </Card>
    </div>
  );
}
