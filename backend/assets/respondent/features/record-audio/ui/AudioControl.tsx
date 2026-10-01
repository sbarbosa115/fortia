import {Button, Field, Icon, Spinner, TextArea} from '@shared/ui';
import {useEffect, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {formatElapsed, waveBars, withSegment} from '../model/recorder';
import {useRecorder} from '../model/useRecorder';
import {MicrophoneHelp} from './MicrophoneHelp';

/**
 * A voice answer (PRD §9.7): each recording adds a text segment (the value is the list of segments), which can be
 * removed or recorded again; "Add to my answer" and "Record again from scratch". Without a working microphone the
 * respondent can type the answer instead (D12).
 */
export function AudioControl({
  label,
  value,
  disabled,
  language,
  onChange,
  onBusyChange,
}: {
  label: string;
  value: string[];
  disabled: boolean;
  language: 'es' | 'en';
  onChange: (segments: string[]) => void;
  onBusyChange: (busy: boolean) => void;
}) {
  const {t} = useTranslation('features.record-audio');
  const recorder = useRecorder(language);
  const [replacing, setReplacing] = useState<number | null>(null);
  const [typing, setTyping] = useState(false);
  const [typed, setTyped] = useState('');
  const busy = recorder.status !== 'idle';

  useEffect(() => {
    onBusyChange(busy);
  }, [busy, onBusyChange]);

  const record = (replaceIndex: number | null) => {
    setReplacing(replaceIndex);
    void recorder.start();
  };
  const stop = async () => {
    const text = await recorder.stop();
    onChange(withSegment(value, text, replacing));
    setReplacing(null);
  };

  return (
    <div className="audio" aria-label={label} role="group">
      {value.length > 0 ? (
        <ol className="audio__segments">
          {value.map((segment, index) => (
            <li key={index} className="audio__segment">
              <span className="audio__segment-title">
                {t('segment', {number: index + 1})}
              </span>
              <p className={segment ? undefined : 'muted'}>
                {segment || t('noTranscription')}
              </p>
              <div className="row">
                <Button
                  size="sm"
                  variant="ghost"
                  icon={<Icon name="trash" />}
                  disabled={disabled || busy}
                  onClick={() => onChange(value.filter((_, i) => i !== index))}
                >
                  {t('remove')}
                </Button>
                <Button
                  size="sm"
                  variant="ghost"
                  icon={<Icon name="refresh" />}
                  disabled={disabled || busy}
                  onClick={() => record(index)}
                >
                  {t('rerecord')}
                </Button>
              </div>
            </li>
          ))}
        </ol>
      ) : null}

      {recorder.status === 'connecting' ? (
        <div className="audio__status" role="status">
          <Spinner size={28} label={t('connecting')} />
          <span>{t('connecting')}</span>
        </div>
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
              <span key={index} style={{transform: `scaleY(${height})`}} />
            ))}
          </div>
          <p role="status">
            {t('listening', {time: formatElapsed(recorder.elapsed)})}
          </p>
          <p className="audio__live" aria-live="polite">
            {recorder.transcript}
          </p>
        </div>
      ) : null}

      {recorder.status === 'idle' && value.length === 0 ? (
        <div className="audio__idle">
          <button
            type="button"
            className="audio__mic"
            disabled={disabled}
            aria-label={t('tapToRecord')}
            onClick={() => record(null)}
          >
            <Icon name="mic" size={36} />
          </button>
          <span>{t('tapToRecord')}</span>
        </div>
      ) : null}

      {recorder.status === 'idle' && value.length > 0 ? (
        <div className="row audio__actions">
          <Button
            icon={<Icon name="plus" />}
            disabled={disabled}
            onClick={() => record(null)}
          >
            {t('addMore')}
          </Button>
          <Button
            variant="ghost"
            disabled={disabled}
            onClick={() => {
              onChange([]);
              record(null);
            }}
          >
            {t('fromScratch')}
          </Button>
        </div>
      ) : null}

      {recorder.error === 'denied' ? (
        <MicrophoneHelp
          onRetry={() => {
            recorder.clearError();
            record(replacing);
          }}
        />
      ) : recorder.error ? (
        <p className="answer-error" role="alert">
          {t(`errors.${recorder.error}`)}
        </p>
      ) : null}

      {!busy && !disabled ? (
        typing ? (
          <div className="stack audio__typed">
            <Field label={t('typeLabel')}>
              <TextArea
                rows={3}
                value={typed}
                onChange={(event) => setTyped(event.target.value)}
              />
            </Field>
            <div className="row">
              <Button
                variant="primary"
                size="sm"
                disabled={typed.trim() === ''}
                onClick={() => {
                  onChange(withSegment(value, typed.trim(), null));
                  setTyped('');
                  setTyping(false);
                }}
              >
                {t('typeAdd')}
              </Button>
              <Button
                size="sm"
                variant="ghost"
                onClick={() => setTyping(false)}
              >
                {t('typeCancel')}
              </Button>
            </div>
          </div>
        ) : (
          <button
            type="button"
            className="link-button"
            onClick={() => setTyping(true)}
          >
            {t('typeInstead')}
          </button>
        )
      ) : null}
    </div>
  );
}
