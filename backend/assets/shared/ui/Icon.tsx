/**
 * The icon set (inline SVG, 24×24 grid, currentColor). Decorative: the button or link around it carries the name.
 * Add an icon by adding its paths here.
 */
const PATHS = {
  'plus': 'M12 5v14M5 12h14',
  'close': 'M6 6l12 12M18 6L6 18',
  'check': 'M5 12l5 5L20 7',
  'edit': 'M4 20h4L19 9l-4-4L4 16v4zM14 6l4 4',
  'trash': 'M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3',
  'copy': 'M8 8h12v12H8zM4 16V4h12',
  'eye':
    'M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12zM12 15a3 3 0 100-6 3 3 0 000 6z',
  'external': 'M14 4h6v6M20 4l-9 9M18 14v6H4V6h6',
  'chevron-left': 'M15 18l-6-6 6-6',
  'chevron-right': 'M9 18l6-6-6-6',
  'chevron-down': 'M6 9l6 6 6-6',
  'chevron-up': 'M18 15l-6-6-6 6',
  'search': 'M11 18a7 7 0 100-14 7 7 0 000 14zM21 21l-5-5',
  'logout': 'M9 21H5V3h4M16 17l5-5-5-5M21 12H9',
  'globe':
    'M12 21a9 9 0 100-18 9 9 0 000 18zM3 12h18M12 3c3 3.5 3 14.5 0 18M12 3c-3 3.5-3 14.5 0 18',
  'sparkles':
    'M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8zM19 16l.8 2.2L22 19l-2.2.8L19 22l-.8-2.2L16 19l2.2-.8z',
  'chart': 'M4 20V10M10 20V4M16 20v-7M22 20H2',
  'users':
    'M16 20v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 10a4 4 0 100-8 4 4 0 000 8zM22 20v-2a4 4 0 00-3-3.9M16 2.1a4 4 0 010 7.8',
  'building': 'M4 21V3h11v18M15 9h5v12M8 7h3M8 11h3M8 15h3M4 21h17',
  'send': 'M22 2L11 13M22 2l-7 20-4-9-9-4z',
  'folder': 'M3 6h6l2 2h10v11H3z',
  'palette':
    'M12 3a9 9 0 000 18c1 0 1.5-.8 1.5-1.5 0-1-.8-1.2-.8-2.2 0-.8.7-1.3 1.5-1.3H17a4 4 0 004-4c0-5-4-9-9-9zM7.5 11a1 1 0 100-2 1 1 0 000 2zM11 7a1 1 0 100-2 1 1 0 000 2zM16 8a1 1 0 100-2 1 1 0 000 2z',
  'plug': 'M9 2v6M15 2v6M6 8h12v4a6 6 0 01-12 0zM12 18v4',
  'user':
    'M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8z',
  'book': 'M4 4h10a4 4 0 014 4v12H8a4 4 0 01-4-4zM18 20h2V4h-6',
  'list': 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01',
  'pencil-ruler': 'M3 21l3-1 12-12-2-2L4 18zM14 6l4 4M17 3l4 4',
  'bell': 'M6 8a6 6 0 1112 0c0 7 3 9 3 9H3s3-2 3-9M10 21h4',
  'download': 'M12 3v12M7 10l5 5 5-5M4 21h16',
  'upload': 'M12 21V9M7 14l5-5 5 5M4 3h16',
  'mic':
    'M12 15a3 3 0 003-3V6a3 3 0 10-6 0v6a3 3 0 003 3zM19 11a7 7 0 01-14 0M12 18v3',
  'stop': 'M6 6h12v12H6z',
  'lock': 'M5 11h14v10H5zM8 11V7a4 4 0 118 0v4',
  'alert':
    'M12 9v4M12 17h.01M10.3 3.9L2 18a2 2 0 001.7 3h16.6a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z',
  'info': 'M12 21a9 9 0 100-18 9 9 0 000 18zM12 16v-4M12 8h.01',
  'grip': 'M9 6h.01M15 6h.01M9 12h.01M15 12h.01M9 18h.01M15 18h.01',
  'more': 'M5 12h.01M12 12h.01M19 12h.01',
  'refresh': 'M20 11a8 8 0 10-2.3 5.7M20 4v7h-7',
  'file': 'M14 3H6v18h12V7zM14 3v4h4',
  'hourglass': 'M6 2h12M6 22h12M7 2v4l5 6-5 6v4M17 2v4l-5 6 5 6v4',
  'menu': 'M3 6h18M3 12h18M3 18h18',
} as const;

export type IconName = keyof typeof PATHS;

export function Icon({name, size = 18}: {name: IconName; size?: number}) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth={1.8}
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      focusable="false"
    >
      <path d={PATHS[name]} />
    </svg>
  );
}
