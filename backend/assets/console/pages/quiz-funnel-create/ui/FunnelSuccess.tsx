import {publicFlowUrl} from '@shared/config';
import {Button, Card, Icon, useToast} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {Link} from 'react-router';
import {type FunnelResult, themeEditorUrl} from '../model/quizFunnel';

/**
 * After creating (PRD §10.5): the funnel's public link, and for a store of the e-commerce platform the steps to
 * enable the app embed in its theme (open the theme editor, save, visit the store).
 */
export function FunnelSuccess({
  result,
  shop,
}: {
  result: FunnelResult;
  shop: string | null;
}) {
  const {t} = useTranslation('pages.quiz-funnel-create');
  const toast = useToast();
  const url = publicFlowUrl(result.flow.slug ?? result.flow.id);
  const absolute = new URL(url, window.location.origin).toString();

  const copyLink = () => {
    const done = navigator.clipboard?.writeText(absolute);
    if (!done) {
      toast.error(t('success.linkNotCopied'));
      return;
    }
    done.then(
      () => toast.success(t('success.linkCopied')),
      () => toast.error(t('success.linkNotCopied')),
    );
  };

  return (
    <div className="quiz-funnel quiz-funnel--success">
      <Card className="quiz-funnel__success">
        <span className="quiz-funnel__success-icon" aria-hidden>
          <Icon name="check" size={28} />
        </span>
        <h1 className="serif-heading quiz-funnel__success-title">
          {t('success.title')}
        </h1>
        <p className="muted">{t('success.body')}</p>
        <p className="quiz-funnel__link">{absolute}</p>
        <div className="row quiz-funnel__success-actions">
          <Button icon={<Icon name="copy" />} onClick={copyLink}>
            {t('success.copyLink')}
          </Button>
          <a
            className="btn btn--secondary"
            href={url}
            target="_blank"
            rel="noopener noreferrer"
          >
            <Icon name="external" />
            {t('success.view')}
          </a>
          <Link
            className="btn btn--secondary"
            to={`/questionnaires/${result.flow.questionnaire_id}/edit`}
          >
            {t('success.edit')}
          </Link>
          <Link className="btn btn--secondary" to="/questionnaires">
            {t('success.list')}
          </Link>
        </div>
        {shop ? (
          <section
            className="quiz-funnel__embed"
            aria-labelledby="quiz-funnel-embed-title"
          >
            <h2 id="quiz-funnel-embed-title">{t('embed.title')}</h2>
            <ol>
              <li>
                {t('embed.open')}{' '}
                <a
                  href={themeEditorUrl(shop)}
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  {t('embed.openLink')}
                </a>
              </li>
              <li>{t('embed.save')}</li>
              <li>
                {t('embed.visit')}{' '}
                <a
                  href={`https://${shop}`}
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  {shop}
                </a>
              </li>
            </ol>
          </section>
        ) : null}
      </Card>
    </div>
  );
}
