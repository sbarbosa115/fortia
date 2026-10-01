import {
  bannerTier,
  usePlanUsage,
  usageRows,
} from '@console/entities/plan-usage';
import {useViewer} from '@console/entities/viewer';
import {useState} from 'react';

export type UsageBannerState = {
  visible: boolean;
  /** The highest usage of the plan's rows, in percent (capped at 100). */
  percent: number;
  /** How many rows are at 50 % or more ("See all (N)"). */
  count: number;
  tone: 'warning' | 'danger';
  dismiss: () => void;
};

/**
 * The usage banner (PRD §10.21): shown when some row reaches 50 %, in tiers 50 / 75 / 90 / 100 (red from 90).
 * Dismissing it silences it until the next tier; nothing is persisted.
 */
export function useUsageBanner(): UsageBannerState {
  const viewer = useViewer();
  const {data} = usePlanUsage(viewer.signedIn && !viewer.isAdmin);
  const [dismissedTier, setDismissedTier] = useState<number | null>(null);

  const rows = data ? usageRows(data).filter((row) => !row.unlimited) : [];
  const percent = rows.reduce((max, row) => Math.max(max, row.percent), 0);
  const tier = bannerTier(percent);
  const count = rows.filter((row) => row.percent >= 50).length;

  return {
    visible:
      !viewer.isAdmin &&
      tier !== null &&
      (dismissedTier === null || tier > dismissedTier),
    percent,
    count,
    tone: tier !== null && tier >= 90 ? 'danger' : 'warning',
    dismiss: () => setDismissedTier(tier),
  };
}
