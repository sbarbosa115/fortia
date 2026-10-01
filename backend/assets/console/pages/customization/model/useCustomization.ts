import {useViewer} from '@console/entities/viewer';
import {api, type Job, pollJob, type Schema} from '@shared/api';
import {useToast} from '@shared/ui';
import {useMutation, useQuery, useQueryClient} from '@tanstack/react-query';
import {useEffect, useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {
  type BrandForm,
  DEFAULT_BRAND,
  formFromStyles,
  googleFontUrl,
  previewTheme,
  savePayload,
  type Styles,
  validateForm,
  websiteChanged,
} from './brand';

type StylesResponse = Schema<'StylesOutput'>;
type JobEnvelope = Schema<'JobEnvelopeOutput'>;

/** PRD §11: the styles job is polled every 5 s for up to 2 min. */
const POLL = {intervalMs: 5_000, timeoutMs: 120_000};

export function stylesQueryKey(customerId: string) {
  return ['console', 'styles', customerId] as const;
}

/**
 * The state of /customization (PRD §10.13): the stored brand loaded into the form, the live preview, Reset (local
 * only) and Save (the styles job, polled; then the brand is read again).
 */
export function useCustomization() {
  const {t} = useTranslation('pages.customization');
  const {t: tShared} = useTranslation('shared');
  const viewer = useViewer();
  const toast = useToast();
  const queryClient = useQueryClient();
  const key = stylesQueryKey(viewer.customerId);
  const stored = useQuery({
    queryKey: key,
    queryFn: () =>
      api.get<StylesResponse>('/styles', {
        query: {customer_id: viewer.customerId},
      }),
    enabled: viewer.customerId !== '',
  });
  const [form, setForm] = useState<BrandForm | null>(null);
  const [loadedFrom, setLoadedFrom] = useState<StylesResponse | null>(null);
  const [attempted, setAttempted] = useState(false);
  const [stage, setStage] = useState<string | null>(null);

  // Load the stored brand into the form whenever it is (re)read (state adjusted while rendering, not in an effect).
  if (stored.data && stored.data !== loadedFrom) {
    setLoadedFrom(stored.data);
    setForm(
      formFromStyles(
        (stored.data.styles as Styles | null) ?? null,
        stored.data.website,
      ),
    );
  }

  const current = form ?? formFromStyles(null, null);
  const errors = useMemo(() => validateForm(current), [current]);
  const storedStyles = (stored.data?.styles as Styles | null) ?? null;
  const theme = useMemo(
    () => previewTheme(storedStyles, current),
    [storedStyles, current],
  );
  usePreviewFont(theme.font);

  const save = useMutation({
    mutationFn: async () => {
      setStage(null);
      const {job} = await api.post<JobEnvelope>(
        '/styles',
        savePayload(current, stored.data?.website),
      );
      return pollJob(job.job_id, {
        ...POLL,
        onUpdate: (polled: Job) => setStage(polled.stage ?? null),
      });
    },
    onSuccess: async () => {
      setStage(null);
      toast.success(t('saved'));
      await queryClient.invalidateQueries({queryKey: key});
    },
    onError: (failure) => {
      setStage(null);
      toast.apiError(failure);
    },
  });

  const readOnlyReason = viewer.canWrite ? null : tShared('readOnly.change');

  return {
    loading: stored.isPending,
    loadError: stored.isError ? stored.error : null,
    retry: () => void stored.refetch(),
    form: current,
    errors,
    showErrors: attempted,
    theme,
    readOnly: !viewer.canWrite,
    saveDisabledReason: readOnlyReason,
    readsWebsite: websiteChanged(current, stored.data?.website),
    saving: save.isPending,
    stage,
    setField: (change: Partial<BrandForm>) =>
      setForm((previous) => ({...(previous ?? current), ...change})),
    reset: () => {
      setForm((previous) => ({...(previous ?? current), ...DEFAULT_BRAND}));
      toast.success(t('resetDone'));
    },
    submit: () => {
      setAttempted(true);
      if (Object.keys(errors).length > 0) {
        toast.error(t('fixErrors'));
        return;
      }
      save.mutate();
    },
  };
}

export type CustomizationState = ReturnType<typeof useCustomization>;

const PREVIEW_FONT_ID = 'customization-preview-font';

/** Loads the previewed font from Google Fonts (the provider the respondent app uses, §9.15). */
function usePreviewFont(family: string) {
  useEffect(() => {
    if (typeof document === 'undefined') {
      return;
    }
    let link = document.getElementById(
      PREVIEW_FONT_ID,
    ) as HTMLLinkElement | null;
    if (!link) {
      link = document.createElement('link');
      link.id = PREVIEW_FONT_ID;
      link.rel = 'stylesheet';
      document.head.appendChild(link);
    }
    link.href = googleFontUrl(family);
  }, [family]);
  useEffect(() => () => document.getElementById(PREVIEW_FONT_ID)?.remove(), []);
}
