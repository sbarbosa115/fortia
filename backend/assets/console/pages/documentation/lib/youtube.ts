const HOSTS = new Set([
  'youtube.com',
  'www.youtube.com',
  'm.youtube.com',
  'youtube-nocookie.com',
  'www.youtube-nocookie.com',
  'youtu.be',
  'www.youtu.be',
]);
const VIDEO_ID = /^[A-Za-z0-9_-]{11}$/;

/** The 11-character id of a YouTube link (watch?v=, youtu.be/, embed/, shorts/), as the backend accepts it. */
export function youtubeId(url: string): string | null {
  let parsed: URL;
  try {
    parsed = new URL(url.trim());
  } catch {
    return null;
  }
  if (!['http:', 'https:'].includes(parsed.protocol)) {
    return null;
  }
  const host = parsed.hostname.toLowerCase();
  if (!HOSTS.has(host)) {
    return null;
  }
  let candidate: string;
  if (host.endsWith('youtu.be')) {
    candidate = parsed.pathname.replace(/^\/+/, '');
  } else if (parsed.pathname.replace(/\/$/, '') === '/watch') {
    candidate = parsed.searchParams.get('v') ?? '';
  } else {
    const match = /^\/(embed|shorts|live)\/([^/]+)\/?$/.exec(parsed.pathname);
    candidate = match?.[2] ?? '';
  }
  return VIDEO_ID.test(candidate) ? candidate : null;
}

/** The privacy-enhanced player URL (youtube-nocookie.com, PRD §10.18), or null when the link is not a video. */
export function embedUrl(url: string): string | null {
  const id = youtubeId(url);
  return id === null ? null : `https://www.youtube-nocookie.com/embed/${id}`;
}
