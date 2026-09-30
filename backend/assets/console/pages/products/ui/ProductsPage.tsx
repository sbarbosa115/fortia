import {Placeholder} from '@shared/ui';
import {useTranslation} from 'react-i18next';

export function ProductsPage() {
  const {t} = useTranslation('pages.products');
  return <Placeholder title={t('title')} />;
}
