import type {Schema} from '@shared/api';

export type CustomerSettings = Schema<'CustomerSettingsOutput'>;
export type Language = CustomerSettings['language'];

export const TRACKING_FIELDS = [
  'pixel_id',
  'linkedin_partner_id',
  'linkedin_conversion_id',
  'google_ads_id',
  'google_ads_conversion_label',
] as const;
export type TrackingField = (typeof TRACKING_FIELDS)[number];
export const TRACKING_MAX = 64;

export type SettingsForm = {language: Language; max_files: string} & Record<
  TrackingField,
  string
>;

export type SettingsPatch = {
  language: Language;
  max_files?: number;
} & Partial<Record<TrackingField, string | null>>;

export function formFrom(settings: CustomerSettings): SettingsForm {
  return {
    language: settings.language,
    max_files: String(settings.max_files),
    pixel_id: settings.pixel_id ?? '',
    linkedin_partner_id: settings.linkedin_partner_id ?? '',
    linkedin_conversion_id: settings.linkedin_conversion_id ?? '',
    google_ads_id: settings.google_ads_id ?? '',
    google_ads_conversion_label: settings.google_ads_conversion_label ?? '',
  };
}

/** "Maximum files per question": a whole number from 1 to 20 (PRD §10.14). */
export function isValidMaxFiles(value: string): boolean {
  const trimmed = value.trim();
  if (!/^\d+$/.test(trimmed)) {
    return false;
  }
  const number = Number(trimmed);
  return number >= 1 && number <= 20;
}

export function isValidForm(form: SettingsForm): boolean {
  return (
    isValidMaxFiles(form.max_files) &&
    TRACKING_FIELDS.every((field) => form[field].trim().length <= TRACKING_MAX)
  );
}

/**
 * What the Settings tab sends (PRD §10.14): the language always, max_files only if it changed, and only the tracking
 * ids that changed (empty → null).
 */
export function buildPatch(
  initial: SettingsForm,
  form: SettingsForm,
): SettingsPatch {
  const patch: SettingsPatch = {language: form.language};
  if (Number(form.max_files.trim()) !== Number(initial.max_files)) {
    patch.max_files = Number(form.max_files.trim());
  }
  for (const field of TRACKING_FIELDS) {
    const value = form[field].trim();
    if (value !== initial[field].trim()) {
      patch[field] = value === '' ? null : value;
    }
  }
  return patch;
}
