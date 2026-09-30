import {useTranslation} from 'react-i18next';
import {PageHeader} from './Card';

/** A page whose item has not been built yet (every route exists from the start; its owner replaces this). */
export function Placeholder({title}: {title: string}) {
  const {t} = useTranslation('shared');
  return (
    <div>
      <PageHeader title={title} />
      <p className="muted">{t('states.comingSoon')}</p>
    </div>
  );
}
