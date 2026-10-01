import {Icon, IconButton} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {Link} from 'react-router';
import {useUsageBanner} from '../model/useUsageBanner';
import './usage-banner.css';

/** The plan usage banner above every console page (PRD §10.21). */
export function UsageBanner() {
  const {t} = useTranslation('widgets.usage-banner');
  const banner = useUsageBanner();
  if (!banner.visible) {
    return null;
  }
  return (
    <div
      className={`usage-banner usage-banner--${banner.tone}`}
      role="status"
      aria-label={t('label')}
    >
      <Icon name="alert" size={18} />
      <p className="usage-banner__text">
        {t('text', {percent: banner.percent})}
      </p>
      <div className="usage-banner__actions">
        <Link className="btn btn--sm btn--ghost" to="/profile">
          {t('seeAll', {count: banner.count})}
        </Link>
        <Link className="btn btn--sm btn--primary" to="/profile/plans">
          {t('upgrade')}
        </Link>
        <IconButton
          size="sm"
          label={t('dismiss')}
          icon={<Icon name="close" size={14} />}
          onClick={banner.dismiss}
        />
      </div>
    </div>
  );
}
