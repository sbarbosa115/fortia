import {describe, expect, it} from 'vitest';
import {
  buildPatch,
  formFrom,
  isValidMaxFiles,
  type SettingsForm,
} from './settingsForm';

const SETTINGS = {
  language: 'es-CO' as const,
  transcription_url: null,
  pixel_id: '123',
  linkedin_partner_id: null,
  linkedin_conversion_id: null,
  google_ads_id: 'AW-1',
  google_ads_conversion_label: null,
  max_files: 10,
};

describe('account settings form (PRD §10.14 Settings)', () => {
  it('accepts only a whole number from 1 to 20', () => {
    expect(['1', '20', ' 7 '].map(isValidMaxFiles)).toEqual([true, true, true]);
    expect(['0', '21', '2.5', '', 'abc', '-3'].map(isValidMaxFiles)).toEqual([
      false,
      false,
      false,
      false,
      false,
      false,
    ]);
  });

  it('sends the language always, and only what changed of the rest', () => {
    const initial = formFrom(SETTINGS);
    const form: SettingsForm = {
      ...initial,
      pixel_id: '',
      linkedin_partner_id: 'LI-9',
    };

    expect(buildPatch(initial, form)).toEqual({
      language: 'es-CO',
      pixel_id: null,
      linkedin_partner_id: 'LI-9',
    });
  });

  it('sends max_files only when it changed, as a number', () => {
    const initial = formFrom(SETTINGS);

    expect(buildPatch(initial, {...initial, max_files: '10'})).toEqual({
      language: 'es-CO',
    });
    expect(
      buildPatch(initial, {...initial, max_files: '4', language: 'en-US'}),
    ).toEqual({
      language: 'en-US',
      max_files: 4,
    });
  });
});
