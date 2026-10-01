import type {Language} from '@shared/i18n';
import {useQuery} from '@tanstack/react-query';
import {useEffect} from 'react';
import {useTranslation} from 'react-i18next';
import {
  type CustomerSettings,
  DEFAULT_MAX_FILES,
  fetchSettings,
  fetchStyles,
} from '../api/brand';
import {configureTracking} from '../lib/tracking';
import {resolveLanguage} from './language';
import {applyBrandTheme, type BrandTheme, brandTheme} from './theme';

export type AccountBrand = {
  /** The brand is applied (or there is none): the neutral skeleton can go (PRD §9.2 "Global"). */
  ready: boolean;
  logoUrl: string | null;
  settings: CustomerSettings | null;
  maxFiles: number;
};

export function accountBrandQueryKey(customerId: string | null) {
  return ['respondent', 'account', customerId] as const;
}

/**
 * The account that owns the questionnaire: its styles become the theme (§9.15), its language the UI language
 * unless the respondent picked one, its tracking ids the pixels (§9.16) and its max_files the file limit (§9.9).
 * Without a customer yet, nothing is applied and the brand is not ready.
 */
export function useAccountBrand(customerId: string | null): AccountBrand {
  const {i18n} = useTranslation();
  const query = useQuery({
    queryKey: accountBrandQueryKey(customerId),
    queryFn: async (): Promise<{
      theme: BrandTheme;
      settings: CustomerSettings | null;
    }> => {
      const [styles, settings] = await Promise.all([
        fetchStyles(customerId ?? ''),
        fetchSettings(customerId ?? ''),
      ]);
      return {theme: brandTheme(styles), settings};
    },
    enabled: customerId !== null,
    staleTime: Infinity,
    retry: false,
  });
  const data = query.data;

  useEffect(() => {
    if (!data) {
      return;
    }
    applyBrandTheme(data.theme);
    configureTracking(data.settings);
    const language = resolveLanguage(
      i18n.language as Language,
      data.settings?.language,
    );
    if (language !== i18n.language) {
      void i18n.changeLanguage(language);
    }
  }, [data, i18n]);

  return {
    ready: data !== undefined,
    logoUrl: data?.theme.logoUrl ?? null,
    settings: data?.settings ?? null,
    maxFiles: data?.settings?.max_files ?? DEFAULT_MAX_FILES,
  };
}
