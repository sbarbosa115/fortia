import {Button, EmptyState} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {useRouteError} from 'react-router';

/** An unhandled error: "Algo salió mal / Something went wrong" with "Recargar / Reload" (PRD §9.2). */
export function RouteError() {
  const {t} = useTranslation('app');
  const error = useRouteError();
  if (error) {
    console.error(error);
  }
  return (
    <div className="boot-skeleton">
      <EmptyState
        title={t('error.title')}
        action={
          <Button variant="primary" onClick={() => window.location.reload()}>
            {t('error.reload')}
          </Button>
        }
      />
    </div>
  );
}
