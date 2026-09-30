import {endSession, stopAssuming} from '@console/entities/viewer';
import {Button, Card} from '@shared/ui';
import {useQueryClient} from '@tanstack/react-query';
import {useTranslation} from 'react-i18next';
import {useNavigate} from 'react-router';

/** "Sign Out?" confirmation card (PRD §10.2). */
export function LogoutPage() {
  const {t} = useTranslation('pages.logout');
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const signOut = () => {
    stopAssuming();
    endSession();
    queryClient.clear();
    navigate('/login', {replace: true});
  };
  return (
    <div className="auth-screen">
      <Card className="auth-card">
        <div className="stack">
          <h1 className="auth-title serif-heading">{t('title')}</h1>
          <p className="muted">{t('body')}</p>
          <div className="row">
            <Button onClick={() => navigate(-1)}>{t('cancel')}</Button>
            <Button variant="primary" onClick={signOut}>
              {t('confirm')}
            </Button>
          </div>
        </div>
      </Card>
    </div>
  );
}
