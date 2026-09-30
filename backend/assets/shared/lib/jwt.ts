/** The claims of a JWT, without verifying it (the API verifies; the app only reads expiry and identity). */
export function decodeJwt<T = Record<string, unknown>>(
  token: string,
): T | null {
  const part = token.split('.')[1];
  if (!part) {
    return null;
  }
  try {
    const base64 = part.replace(/-/g, '+').replace(/_/g, '/');
    const padded = base64.padEnd(
      base64.length + ((4 - (base64.length % 4)) % 4),
      '=',
    );
    const json = decodeURIComponent(
      Array.from(
        atob(padded),
        (c) => `%${c.charCodeAt(0).toString(16).padStart(2, '0')}`,
      ).join(''),
    );
    return JSON.parse(json) as T;
  } catch {
    return null;
  }
}
