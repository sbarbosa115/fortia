import {Button, EmptyState} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {useRouteError} from 'react-router';

/** An unexpected error while rendering a route: a way out instead of a blank screen. */
export function RouteError() {
  const {t} = useTranslation('app');
  const error = useRouteError();
  if (error) {
    console.error(error);
  }
  return (
    <EmptyState
      title={t('error.title')}
      action={
        <Button variant="primary" onClick={() => window.location.reload()}>
          {t('error.reload')}
        </Button>
      }
    />
  );
}
