import {describe, expect, it} from 'vitest';
import {embedUrl, youtubeId} from './youtube';

describe('embedUrl (PRD §10.18: videos embedded in privacy-enhanced mode)', () => {
  it('turns every usual YouTube link into a youtube-nocookie player URL', () => {
    for (const url of [
      'https://www.youtube.com/watch?v=abcDEF12345',
      'https://youtube.com/watch?feature=share&v=abcDEF12345',
      'https://youtu.be/abcDEF12345?t=10',
      'https://www.youtube.com/embed/abcDEF12345',
      'https://www.youtube.com/shorts/abcDEF12345',
    ]) {
      expect(embedUrl(url), url).toBe(
        'https://www.youtube-nocookie.com/embed/abcDEF12345',
      );
    }
  });

  it('refuses links that are not YouTube videos', () => {
    for (const url of [
      'https://vimeo.com/123456',
      'https://www.youtube.com/',
      'https://youtube.com.evil.test/watch?v=abcDEF12345',
      'javascript:alert(1)',
      'not a url',
    ]) {
      expect(youtubeId(url), url).toBeNull();
      expect(embedUrl(url), url).toBeNull();
    }
  });
});
