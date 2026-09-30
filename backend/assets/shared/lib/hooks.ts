import {useEffect, useState} from 'react';

/** The value, once it stopped changing for delayMs (300 ms searches, PRD §10.11, §10.12). */
export function useDebouncedValue<T>(value: T, delayMs = 300): T {
  const [debounced, setDebounced] = useState(value);
  useEffect(() => {
    const timer = setTimeout(() => setDebounced(value), delayMs);
    return () => clearTimeout(timer);
  }, [value, delayMs]);
  return debounced;
}

/** Sets document.title ("Mappi - {title}" in the console, "<Brand> - {title}" in the respondent app). */
export function useDocumentTitle(title: string | null | undefined): void {
  useEffect(() => {
    if (title) {
      document.title = title;
    }
  }, [title]);
}
