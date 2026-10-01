import {GoogleSignInButton} from '@console/features/google-sign-in';
import {useViewer} from '@console/entities/viewer';
import {useDocumentTitle} from '@shared/lib';
import {Badge, Tabs} from '@shared/ui';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {Navigate, useSearchParams} from 'react-router';
import {authErrorKey, safeNext} from '../model/auth';
import {BrandPanel} from './BrandPanel';
import {LegalLinks} from './LegalLinks';
import {SignInForm} from './SignInForm';
import {SignUpForm} from './SignUpForm';
import './login.css';

type Mode = 'signin' | 'signup';

/**
 * /login (PRD §10.2): tabs "Sign in" / "Sign up" (?mode=signup), Google, the strength meter on sign-up and the
 * brand panel. A signed-in visitor goes straight to where they were heading.
 */
export function LoginPage() {
  const {t} = useTranslation('pages.login');
  const viewer = useViewer();
  const [params, setParams] = useSearchParams();
  const [googleError, setGoogleError] = useState<string | null>(null);
  const mode: Mode = params.get('mode') === 'signup' ? 'signup' : 'signin';
  const next = params.get('next');
  useDocumentTitle(
    `Mappi - ${mode === 'signup' ? t('tabs.signUp') : t('tabs.signIn')}`,
  );

  if (viewer.signedIn) {
    return <Navigate to={safeNext(next)} replace />;
  }

  const changeMode = (value: Mode) => {
    const nextParams = new URLSearchParams(params);
    if (value === 'signup') {
      nextParams.set('mode', 'signup');
    } else {
      nextParams.delete('mode');
    }
    setGoogleError(null);
    setParams(nextParams, {replace: true});
  };

  return (
    <div className="login">
      <main className="login__main">
        <div className="login__content">
          <div className="login__brand">
            <span className="login__logo" aria-hidden>
              {'M'}
            </span>
            <span className="login__product">{t('product')}</span>
          </div>
          <Badge tone="accent">{t('badge')}</Badge>
          <h1 className="login__title serif-heading">
            {mode === 'signup' ? t('signUp.title') : t('signIn.title')}
          </h1>
          <p className="muted login__subtitle">
            {mode === 'signup' ? t('signUp.subtitle') : t('signIn.subtitle')}
          </p>
          <Tabs<Mode>
            label={t('tabs.label')}
            active={mode}
            onChange={changeMode}
            tabs={[
              {key: 'signin', label: t('tabs.signIn')},
              {key: 'signup', label: t('tabs.signUp')},
            ]}
          />
          <div role="tabpanel" className="stack login__panel">
            <GoogleSignInButton
              next={next}
              onError={(error) => setGoogleError(t(authErrorKey(error)))}
            />
            {googleError ? (
              <p className="field__error" role="alert">
                {googleError}
              </p>
            ) : null}
            <div className="login__divider">
              <span>{t('or')}</span>
            </div>
            {mode === 'signup' ? (
              <SignUpForm
                next={next}
                onSwitchToSignIn={() => changeMode('signin')}
              />
            ) : (
              <SignInForm next={next} />
            )}
          </div>
        </div>
        <LegalLinks />
      </main>
      <BrandPanel />
    </div>
  );
}
