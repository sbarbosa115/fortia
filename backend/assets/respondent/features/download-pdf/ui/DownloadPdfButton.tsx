import {Button, Icon} from '@shared/ui';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {downloadReport, type PdfReport} from '../model/pdf';

/** "Download" → "Preparing your PDF…" while the report is built (PRD §9.12). */
export function DownloadPdfButton({
  label,
  report,
}: {
  label: string;
  report: () => PdfReport;
}) {
  const {t} = useTranslation('features.download-pdf');
  const [busy, setBusy] = useState(false);
  const [failed, setFailed] = useState(false);
  return (
    <div className="stack">
      <Button
        variant="primary"
        icon={<Icon name="download" />}
        loading={busy}
        onClick={() => {
          setBusy(true);
          setFailed(false);
          downloadReport(report())
            .catch(() => setFailed(true))
            .finally(() => setBusy(false));
        }}
      >
        {busy ? t('preparing') : label}
      </Button>
      {failed ? (
        <p className="answer-error" role="alert">
          {t('failed')}
        </p>
      ) : null}
    </div>
  );
}
