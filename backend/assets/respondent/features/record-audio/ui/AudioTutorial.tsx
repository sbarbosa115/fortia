import {Button, Card, Icon, Spinner} from '@shared/ui';
import {useEffect, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {waveBars} from '../model/recorder';
import {useRecorder} from '../model/useRecorder';
import {MicrophoneHelp} from './MicrophoneHelp';

type Result = {kind: 'heard'; text: string} | {kind: 'empty'} | null;

/**
 * The mic check before voice questions, once (PRD §9.8): read a sentence out loud, see what was heard. The
 * respondent can always skip it (D12): a respondent without a microphone is never stuck.
 */
export function AudioTutorial({
  language,
  onDone,
}: {
  language: 'es' | 'en';
  onDone: () => void;
}) {
  const {t} = useTranslation('features.record-audio');
  const recorder = useRecorder(language);
  const [result, setResult] = useState<Result>(null);

  const stop = async () => {
    const text = await recorder.stop();
    setResult(text ? {kind: 'heard', text} : {kind: 'empty'});
  };
  const retry = () => {
    setResult(null);
    recorder.clearError();
    void recorder.start();
  };

  useEffect(() => {
    if (recorder.error && recorder.error !== 'denied') {
      // Reported to error tracking (§9.8): the console is where the tracker picks it up.
      console.error('Mic check failed', recorder.error);
    }
  }, [recorder.error]);

  return (
    <Card className="tutorial">
      <div className="card__body stack">
        <span className="eyebrow">{t('tutorial.eyebrow')}</span>
        <h1 className="serif-heading tutorial__title">{t('tutorial.title')}</h1>
        {result?.kind === 'heard' ? (
          <div className="stack" role="status">
            <p className="tutorial__success">
              <Icon name="check" /> {t('tutorial.success')}
            </p>
            <p>{t('tutorial.successBody')}</p>
            <p className="muted">{t('tutorial.heard')}</p>
            <blockquote className="tutorial__quote">{result.text}</blockquote>
            <Button variant="primary" onClick={onDone}>
              {t('tutorial.continue')}
            </Button>
          </div>
        ) : (
          <>
            <p>{t('tutorial.body')}</p>
            <p className="muted">{t('tutorial.readAloud')}</p>
            <blockquote className="tutorial__quote">
              {t('tutorial.sentence')}
            </blockquote>
            {recorder.status === 'connecting' ? (
              <p role="status" className="row">
                <Spinner size={18} /> {t('tutorial.preparing')}
              </p>
            ) : null}
            {recorder.status === 'recording' ? (
              <div className="audio__recording">
                <button
                  type="button"
                  className="audio__mic audio__mic--stop"
                  aria-label={t('stop')}
                  onClick={() => void stop()}
                >
                  <Icon name="stop" size={28} />
                </button>
                <div className="audio__bars" aria-hidden="true">
                  {waveBars(recorder.level).map((height, index) => (
                    <span
                      key={index}
                      style={{transform: `scaleY(${height})`}}
                    />
                  ))}
                </div>
                <p role="status">{t('tutorial.listening')}</p>
                <p className="audio__live" aria-live="polite">
                  {recorder.transcript}
                </p>
              </div>
            ) : null}
            {recorder.status === 'idle' ? (
              <div className="audio__idle">
                <button
                  type="button"
                  className="audio__mic"
                  aria-label={t('tutorial.prompt')}
                  onClick={retry}
                >
                  <Icon name="mic" size={36} />
                </button>
                <span>{t('tutorial.prompt')}</span>
              </div>
            ) : null}
            {result?.kind === 'empty' ? (
              <p className="answer-error" role="alert">
                {t('tutorial.empty')}
              </p>
            ) : null}
            {recorder.error === 'denied' ? (
              <MicrophoneHelp onRetry={retry} />
            ) : recorder.error ? (
              <div className="answer-error" role="alert">
                <p>{t('tutorial.failed')}</p>
                <p>{t(`errors.${recorder.error}`)}</p>
              </div>
            ) : null}
          </>
        )}
        <Button variant="ghost" onClick={onDone}>
          {t('tutorial.skip')}
        </Button>
      </div>
    </Card>
  );
}
