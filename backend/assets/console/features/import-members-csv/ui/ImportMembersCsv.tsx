import type {MemberDraft} from '@console/entities/organization';
import {Button, Icon, useToast} from '@shared/ui';
import {type ChangeEvent, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {type ImportResult, parseMembersCsv} from '../lib/parseMembersCsv';
import {downloadTemplate} from '../lib/template';
import './import-members-csv.css';

/**
 * "Import CSV" and "Download template" for the member list (PRD §10.10): the file's members are added to the
 * list; the rows skipped are listed with their line and reason.
 */
export function ImportMembersCsv({
  existing,
  onImport,
  disabled = false,
}: {
  existing: MemberDraft[];
  onImport: (members: MemberDraft[]) => void;
  disabled?: boolean;
}) {
  const {t} = useTranslation('features.import-members-csv');
  const toast = useToast();
  const input = useRef<HTMLInputElement>(null);
  const [result, setResult] = useState<ImportResult | null>(null);

  const onFile = async (event: ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) {
      return;
    }
    const parsed = parseMembersCsv(await file.text(), existing);
    setResult(parsed);
    if (parsed.error) {
      toast.error(t(`errors.${parsed.error}`));
      return;
    }
    if (parsed.members.length > 0) {
      onImport(parsed.members);
      toast.success(t('imported', {count: parsed.members.length}));
    }
  };

  return (
    <div className="csv-import">
      <div className="row">
        <Button
          size="sm"
          icon={<Icon name="upload" size={16} />}
          disabled={disabled}
          onClick={() => input.current?.click()}
        >
          {t('import')}
        </Button>
        <Button
          size="sm"
          variant="ghost"
          icon={<Icon name="download" size={16} />}
          onClick={downloadTemplate}
        >
          {t('template')}
        </Button>
        <input
          ref={input}
          type="file"
          accept=".csv,text/csv"
          className="visually-hidden"
          aria-label={t('fileLabel')}
          tabIndex={-1}
          onChange={(event) => void onFile(event)}
        />
      </div>
      <p className="field__hint">{t('hint')}</p>
      {result ? <ImportReport result={result} /> : null}
    </div>
  );
}

function ImportReport({result}: {result: ImportResult}) {
  const {t} = useTranslation('features.import-members-csv');
  if (result.error) {
    return (
      <p className="csv-import__report csv-import__report--error" role="alert">
        {t(`errors.${result.error}`)}
      </p>
    );
  }
  return (
    <div className="csv-import__report" role="status">
      <p>{t('imported', {count: result.members.length})}</p>
      {result.skipped.length > 0 ? (
        <>
          <p>{t('skippedTitle', {count: result.skipped.length})}</p>
          <ul className="csv-import__skipped">
            {result.skipped.map((row) => (
              <li key={row.line}>
                {t('skippedRow', {
                  line: row.line,
                  name: row.name || t('noName'),
                  reason: t(`reasons.${row.reason}`),
                })}
              </li>
            ))}
          </ul>
        </>
      ) : null}
    </div>
  );
}
