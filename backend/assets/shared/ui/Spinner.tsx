import {useTranslation} from 'react-i18next';

export function Spinner({size = 20, label}: {size?: number; label?: string}) {
  const {t} = useTranslation('shared');
  return (
    <span
      className="spinner"
      role="status"
      aria-label={label ?? t('status.loading')}
      style={{width: size, height: size}}
    />
  );
}

export function Skeleton({
  width = '100%',
  height = 16,
}: {
  width?: number | string;
  height?: number | string;
}) {
  return (
    <span
      className="skeleton"
      aria-hidden
      style={{display: 'block', width, height}}
    />
  );
}

/** A full-area loading state for a page or a panel. */
export function LoadingState({label}: {label?: string}) {
  return (
    <div className="state" aria-live="polite">
      <Spinner size={28} label={label} />
    </div>
  );
}
