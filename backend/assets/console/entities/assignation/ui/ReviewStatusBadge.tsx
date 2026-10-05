import {Badge, type Tone} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import type {ReviewStatus} from '../api/assignations';

const TONES: Record<ReviewStatus, Tone> = {
  not_ready: 'neutral',
  in_review: 'accent',
  changes_requested: 'warning',
  approved: 'success',
  completed: 'success',
};

/** A follow-up's review state (PRD §7.11), always with its label. */
export function ReviewStatusBadge({status}: {status: ReviewStatus}) {
  const {t} = useTranslation('entities.assignation');
  return <Badge tone={TONES[status]}>{t(`reviewStatus.${status}`)}</Badge>;
}
