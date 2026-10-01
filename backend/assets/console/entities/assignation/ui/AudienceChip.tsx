import {Badge} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import type {Audience} from '../api/assignations';
import {audienceChip} from '../lib/audience';

/** "Everybody", "2 people", "Area: Sales +1" (PRD §10.11). */
export function AudienceChip({audience}: {audience: Audience}) {
  const {t} = useTranslation('entities.assignation');
  const chip = audienceChip(audience);
  const label =
    chip.key === 'everybody'
      ? t('audience.everybody')
      : chip.key === 'people'
        ? t('audience.people', {count: chip.count})
        : [
            t(`audience.${chip.key}`, {first: chip.first}),
            chip.more > 0 ? t('audience.more', {count: chip.more}) : '',
          ]
            .filter(Boolean)
            .join(' ');
  return <Badge>{label}</Badge>;
}
