import {appConfig} from '@shared/config';
import {
  buildSheetRows,
  exportToSheets,
  formatDateTime,
  type SheetSession,
} from '@shared/lib';
import {Button, Icon, useToast} from '@shared/ui';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';

/**
 * "Export to Google Sheets" (PRD §13.9) for the answers of a questionnaire or an assignation. Disabled, with the
 * reason, while GOOGLE_SHEETS_CLIENT_ID is not configured. The tab is opened on click (so no popup blocker stops it)
 * and pointed at the sheet once it is written.
 */
export function ExportToSheetsButton({
  title,
  exportKey,
  loadSessions,
}: {
  /** The questionnaire's (or assignation's) title: the sheet is "Answers - {title}". */
  title: string;
  /** exportKey(questionnaireId, assignationId?) from @shared/lib/sheets. */
  exportKey: string;
  /** Every session to export (all pages). */
  loadSessions: () => Promise<SheetSession[]>;
}) {
  const {t, i18n} = useTranslation('features.export-to-sheets');
  const toast = useToast();
  const [busy, setBusy] = useState(false);
  const clientId = appConfig().googleSheetsClientId;

  const onExport = async () => {
    const tab = window.open('', '_blank');
    setBusy(true);
    try {
      const rows = buildSheetRows(
        await loadSessions(),
        {
          startedAt: t('columns.startedAt'),
          user: t('columns.user'),
          email: t('columns.email'),
          phone: t('columns.phone'),
          skipped: t('values.skipped'),
          filesUploaded: (count) => t('values.filesUploaded', {count}),
        },
        (iso) => formatDateTime(iso, i18n.language),
      );
      const url = await exportToSheets({
        clientId,
        key: exportKey,
        title: t('sheetTitle', {title}),
        rows,
      });
      if (tab) {
        tab.location.href = url;
      } else {
        window.open(url, '_blank', 'noopener');
      }
      toast.success(t('done'));
    } catch (error) {
      tab?.close();
      if (
        error instanceof Error &&
        /Google|access_denied|popup/.test(error.message)
      ) {
        toast.error(t('failed'));
      } else {
        toast.apiError(error);
      }
    } finally {
      setBusy(false);
    }
  };

  return (
    <Button
      icon={<Icon name="download" />}
      loading={busy}
      disabledReason={clientId ? null : t('notConfigured')}
      onClick={() => void onExport()}
    >
      {t('label')}
    </Button>
  );
}
