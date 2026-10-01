import {stateTone} from '@console/entities/project';
import {Badge} from '@shared/ui';
import {useTranslation} from 'react-i18next';

/** A project's or an assignation's state as a labelled badge (PRD §10.12 state labels). */
export function StateBadge({state}: {state: string}) {
  const {t} = useTranslation('pages.projects');
  return <Badge tone={stateTone(state)}>{t(`state.${state}`)}</Badge>;
}
