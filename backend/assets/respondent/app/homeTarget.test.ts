import {describe, expect, it} from 'vitest';
import {homeTarget} from './homeTarget';

const HERE = 'https://fortia.getmappi.com/';
const config = {
  homeRedirectUrl: '',
  marketingSiteUrl: 'https://getmappi.com',
  consoleUrl: 'https://fortia.getmappi.com/console',
};

describe('homeTarget', () => {
  it('sends "/" to the marketing site (PRD §9.2)', () => {
    expect(homeTarget(config, HERE)).toBe('https://getmappi.com');
  });

  it('prefers HOME_REDIRECT_URL when it is set', () => {
    expect(homeTarget({...config, homeRedirectUrl: '/console'}, HERE)).toBe(
      '/console',
    );
  });

  it('opens the console instead of reloading "/" when the marketing site is the app itself', () => {
    for (const marketingSiteUrl of [
      'https://fortia.getmappi.com',
      'https://fortia.getmappi.com/',
      '/',
    ]) {
      expect(homeTarget({...config, marketingSiteUrl}, HERE)).toBe(
        config.consoleUrl,
      );
    }
    expect(homeTarget({...config, homeRedirectUrl: '/'}, HERE)).toBe(
      config.consoleUrl,
    );
  });

  it('still follows a page of the same domain that is not "/"', () => {
    expect(
      homeTarget(
        {...config, marketingSiteUrl: 'https://fortia.getmappi.com/about'},
        HERE,
      ),
    ).toBe('https://fortia.getmappi.com/about');
  });
});
