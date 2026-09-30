import {type KeyboardEvent, useId} from 'react';

export type TabItem<K extends string> = {key: K; label: string};

/** A tab list (roving focus with the arrow keys). The panel is rendered by the caller under it. */
export function Tabs<K extends string>({
  tabs,
  active,
  onChange,
  label,
}: {
  tabs: TabItem<K>[];
  active: K;
  onChange: (key: K) => void;
  label: string;
}) {
  const id = useId();
  const onKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
    const index = tabs.findIndex((tab) => tab.key === active);
    const step =
      event.key === 'ArrowRight' ? 1 : event.key === 'ArrowLeft' ? -1 : 0;
    if (step === 0) {
      return;
    }
    event.preventDefault();
    const next = tabs[(index + step + tabs.length) % tabs.length];
    if (next) {
      onChange(next.key);
      document.getElementById(`${id}-${next.key}`)?.focus();
    }
  };
  return (
    <div
      className="tabs"
      role="tablist"
      aria-label={label}
      onKeyDown={onKeyDown}
    >
      {tabs.map((tab) => (
        <button
          key={tab.key}
          id={`${id}-${tab.key}`}
          type="button"
          role="tab"
          className="tabs__tab"
          aria-selected={tab.key === active}
          tabIndex={tab.key === active ? 0 : -1}
          onClick={() => onChange(tab.key)}
        >
          {tab.label}
        </button>
      ))}
    </div>
  );
}
