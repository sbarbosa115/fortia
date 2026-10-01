import {Button, Icon} from '@shared/ui';
import {Trans, useTranslation} from 'react-i18next';

/** "Microphone access is blocked": it is a browser permission, how to turn it on, and Try again (PRD §9.7). */
export function MicrophoneHelp({onRetry}: {onRetry: () => void}) {
  const {t} = useTranslation('features.record-audio');
  return (
    <div className="mic-help" role="alert">
      <p className="mic-help__title">
        <Icon name="lock" /> {t('denied.title')}
      </p>
      <p>{t('denied.body')}</p>
      <p>
        <Trans
          t={t}
          i18nKey="denied.step"
          components={{
            lock: <Icon name="lock" size={14} />,
            strong: <strong />,
          }}
        />
      </p>
      <Button variant="primary" size="sm" onClick={onRetry}>
        {t('denied.retry')}
      </Button>
      <p className="muted">{t('denied.note')}</p>
    </div>
  );
}
