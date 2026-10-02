import {publicFlowUrl} from '@shared/config';
import {Button, Icon} from '@shared/ui';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {Link, useNavigate} from 'react-router';
import {useEditorContext} from '../model/EditorContext';
import {useReturnTo} from '../model/returnTo';

/**
 * After saving (PRD §10.5), as in the admin console: a header with the breadcrumb and "Create another", then the
 * card with "Questionnaire created successfully!" / "Your diagnostic is ready." / "Changes saved", the public link
 * with Copy link, View questionnaire, Keep editing and Go to Questionnaires.
 */
export function SuccessScreen() {
  const {t} = useTranslation('pages.questionnaire-editor');
  const editor = useEditorContext();
  const navigate = useNavigate();
  const returnTo = useReturnTo();
  const [copied, setCopied] = useState(false);
  const saved = editor.saved;
  if (!saved) {
    return null;
  }
  const url = new URL(
    publicFlowUrl(saved.slug ?? saved.questionnaireId),
    window.location.origin,
  ).toString();
  const title =
    editor.mode === 'edit'
      ? t('success.saved')
      : editor.draft.kind === 'diagnostic'
        ? t('success.diagnostic')
        : t('success.created');

  const copyLink = () => {
    void navigator.clipboard?.writeText(url).then(() => {
      setCopied(true);
      window.setTimeout(() => setCopied(false), 1500);
    });
  };

  return (
    <div className="cshell">
      <header className="cshell__header">
        <nav
          aria-label={t('breadcrumb.label')}
          className="cshell__crumbs cshell__crumbs--grow"
        >
          <Link to="/ai-experience">{t('breadcrumb.workspace')}</Link>
          <span aria-hidden>{'/'}</span>
          <Link to="/questionnaires">{t('breadcrumb.questionnaires')}</Link>
          <span aria-hidden>{'/'}</span>
          <span>{t(`kind.${editor.draft.kind}`)}</span>
          <span aria-hidden>{'/'}</span>
          <span aria-current="page" className="cshell__current">
            {t('success.crumb')}
          </span>
        </nav>
        <Link
          className="btn btn--secondary cshell__another"
          to="/questionnaires/new"
        >
          <Icon name="plus" size={16} />
          {t('success.another')}
        </Link>
      </header>
      <div className="cshell__main cshell__main--success">
        <div className="success">
          <span className="success__icon" aria-hidden>
            <Icon name="check" size={28} />
          </span>
          <h1 className="serif-heading success__title">{title}</h1>
          <p className="success__subtitle">{t('success.body')}</p>
          <div className="success__link">
            <span className="success__url">{url}</span>
            <Button
              size="sm"
              icon={<Icon name={copied ? 'check' : 'copy'} size={16} />}
              onClick={copyLink}
            >
              {copied ? t('success.linkCopied') : t('success.copyLink')}
            </Button>
          </div>
          <div className="success__actions">
            <a
              className="btn btn--primary"
              href={url}
              target="_blank"
              rel="noopener noreferrer"
            >
              <Icon name="external" size={16} />
              {t('success.view')}
            </a>
            <Button
              onClick={() => {
                if (editor.mode === 'edit') {
                  editor.keepEditing();
                } else {
                  void navigate(editor.editPath(saved.questionnaireId));
                }
              }}
            >
              {t('success.keepEditing')}
            </Button>
            {returnTo ? (
              <Link className="btn btn--ghost success__list" to={returnTo.to}>
                {t(`back.${returnTo.kind}`, {ns: 'shared'})}
              </Link>
            ) : (
              <Link
                className="btn btn--ghost success__list"
                to="/questionnaires"
              >
                {t('success.list')}
              </Link>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
