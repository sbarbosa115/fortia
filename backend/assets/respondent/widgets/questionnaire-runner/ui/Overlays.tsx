import {
  answeredCount,
  progressPercent,
  savedAgo,
  type Session,
} from '@respondent/entities/session';
import {Badge, Button, Icon, Modal, ProgressBar} from '@shared/ui';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';

/**
 * "Before you start": the disclaimer (line breaks kept, scrolls at 45vh) and the "Private" badge; "Accept and
 * continue" saves consent for 24 h, "Not now, thanks" tries to close the tab (PRD §9.3).
 */
export function DisclaimerModal({
  text,
  onAccept,
  onDecline,
}: {
  text: string;
  onAccept: () => void;
  onDecline: () => void;
}) {
  const {t} = useTranslation('widgets.questionnaire-runner');
  return (
    <Modal
      open
      title={t('disclaimer.title')}
      onClose={onDecline}
      footer={
        <>
          <Button variant="ghost" onClick={onDecline}>
            {t('disclaimer.decline')}
          </Button>
          <Button variant="primary" onClick={onAccept}>
            {t('disclaimer.accept')}
          </Button>
        </>
      }
    >
      <div className="stack">
        <Badge tone="success">
          <Icon name="lock" size={14} /> {t('disclaimer.private')}
        </Badge>
        <p className="disclaimer-text">{text}</p>
      </div>
    </Modal>
  );
}

/**
 * "Pick up where you left off" over the landing and the questions when a saved session has progress: how long ago
 * it was saved, the question it stopped at, Continue, and Start over with the warning (PRD §9.3).
 */
export function ResumeModal({
  session,
  position,
  total,
  savedAt,
  onContinue,
  onStartOver,
}: {
  session: Session;
  position: number;
  total: number;
  savedAt: number;
  onContinue: () => void;
  onStartOver: () => void;
}) {
  const {t} = useTranslation('widgets.questionnaire-runner');
  const [now] = useState(() => Date.now());
  const ago = savedAgo(savedAt, now);
  const current = Math.min(position + 1, total);
  const pct = progressPercent(current, total);
  const count = answeredCount(session);
  return (
    <Modal open title={t('resume.title')} onClose={onContinue}>
      <div className="stack">
        <Badge tone="accent">
          {ago.unit === 'now'
            ? t('resume.savedNow')
            : t(`resume.saved.${ago.unit}`, {count: ago.count})}
        </Badge>
        <p>{t('resume.body')}</p>
        <div className="resume-box">
          <div className="resume-box__head">
            <span>{t('resume.question', {current, total})}</span>
            <span>{pct}%</span>
          </div>
          <ProgressBar
            value={current}
            max={total}
            label={t('resume.question', {current, total})}
          />
        </div>
        <Button variant="primary" onClick={onContinue}>
          {t('resume.continue')}
        </Button>
        <button type="button" className="link-button" onClick={onStartOver}>
          {t('resume.startOver')}
        </button>
        <p className="muted resume-warning">{t('resume.warning', {count})}</p>
      </div>
    </Modal>
  );
}
