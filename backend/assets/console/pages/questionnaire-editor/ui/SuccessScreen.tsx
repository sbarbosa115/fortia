import {publicFlowUrl} from '@shared/config';
import {Button, Card, Icon, useToast} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {Link, useNavigate} from 'react-router';
import {useEditorContext} from '../model/EditorContext';

/**
 * After saving (PRD §10.5): "Questionnaire created successfully!" / "Your diagnostic is ready." / "Changes saved",
 * with Copy link, View questionnaire, Keep editing, Go to Questionnaires and Create another.
 */
export function SuccessScreen() {
  const {t} = useTranslation('pages.questionnaire-editor');
  const editor = useEditorContext();
  const toast = useToast();
  const navigate = useNavigate();
  const saved = editor.saved;
  if (!saved) {
    return null;
  }
  const url = publicFlowUrl(saved.slug ?? saved.questionnaireId);
  const absolute = new URL(url, window.location.origin).toString();
  const title =
    editor.mode === 'edit'
      ? t('success.saved')
      : editor.draft.kind === 'diagnostic'
        ? t('success.diagnostic')
        : t('success.created');

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
    <div className="editor editor--success">
      <Card className="success">
        <span className="success__icon" aria-hidden>
          <Icon name="check" size={28} />
        </span>
        <h1 className="serif-heading success__title">{title}</h1>
        <p className="muted">{t('success.body')}</p>
        <p className="success__link">{absolute}</p>
        <div className="row success__actions">
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
          <Link className="btn btn--secondary" to="/questionnaires">
            {t('success.list')}
          </Link>
          <Link className="btn btn--primary" to="/questionnaires/new">
            {t('success.another')}
          </Link>
        </div>
      </Card>
    </div>
  );
}
