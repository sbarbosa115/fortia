import {
  questionnairePublicUrl,
  type QuestionnaireRow,
} from '@console/entities/questionnaire';
import {Badge, Icon, IconButton, useToast} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {Link} from 'react-router';

/** View (new tab), Copy link, Edit, Answers and Analytics ("New"), in the house order (PRD §10.6). */
export function RowActions({
  row,
  readOnlyReason,
}: {
  row: QuestionnaireRow;
  readOnlyReason: string | null;
}) {
  const {t} = useTranslation('pages.questionnaires');
  const toast = useToast();
  const url = questionnairePublicUrl(row);
  const absoluteUrl = new URL(url, window.location.origin).toString();

  const copyLink = () => {
    const done = navigator.clipboard?.writeText(absoluteUrl);
    if (!done) {
      toast.error(t('linkNotCopied'));
      return;
    }
    done.then(
      () => toast.success(t('linkCopied')),
      () => toast.error(t('linkNotCopied')),
    );
  };

  return (
    <>
      <a
        className="btn btn--ghost btn--sm btn--icon"
        href={url}
        target="_blank"
        rel="noopener noreferrer"
        aria-label={t('actions.view', {title: row.title})}
        title={t('actions.view', {title: row.title})}
      >
        <Icon name="eye" />
      </a>
      <IconButton
        size="sm"
        icon={<Icon name="copy" />}
        label={t('actions.copyLink', {title: row.title})}
        onClick={copyLink}
      />
      {readOnlyReason ? (
        <IconButton
          size="sm"
          icon={<Icon name="edit" />}
          label={t('actions.edit', {title: row.title})}
          disabledReason={readOnlyReason}
        />
      ) : (
        <Link
          className="btn btn--ghost btn--sm btn--icon"
          to={`/questionnaires/${row.questionnaire_id}/edit`}
          aria-label={t('actions.edit', {title: row.title})}
          title={t('actions.edit', {title: row.title})}
        >
          <Icon name="edit" />
        </Link>
      )}
      <Link
        className="btn btn--ghost btn--sm"
        to={`/questionnaires/${row.questionnaire_id}/answers`}
      >
        {t('actions.answers')}
      </Link>
      <Link
        className="btn btn--ghost btn--sm"
        to={`/questionnaires/${row.questionnaire_id}/dashboard`}
      >
        <Icon name="chart" />
        {t('actions.analytics')}
        <Badge tone="accent">{t('actions.new')}</Badge>
      </Link>
    </>
  );
}
