import {type ReactNode, useEffect, useRef} from 'react';
import {Icon} from './Icon';

export function FilterBar({children}: {children: ReactNode}) {
  return <div className="filter-bar">{children}</div>;
}

/**
 * The search box of the listings. With shortcut, ⌘/Ctrl+K focuses it (PRD §10.6).
 */
export function SearchInput({
  value,
  onChange,
  label,
  placeholder,
  shortcut = false,
}: {
  value: string;
  onChange: (value: string) => void;
  label: string;
  placeholder?: string;
  shortcut?: boolean;
}) {
  const ref = useRef<HTMLInputElement>(null);
  useEffect(() => {
    if (!shortcut) {
      return;
    }
    const onKey = (event: KeyboardEvent) => {
      if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        ref.current?.focus();
      }
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [shortcut]);
  return (
    <div className="search">
      <span className="search__icon">
        <Icon name="search" size={16} />
      </span>
      <input
        ref={ref}
        type="search"
        className="input"
        aria-label={label}
        placeholder={placeholder}
        value={value}
        onChange={(event) => onChange(event.target.value)}
      />
    </div>
  );
}
