import {useDocumentTitle} from '@shared/lib';
import {Badge, Button, Icon} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {Link} from 'react-router';
import {useEditorContext} from '../model/EditorContext';
import {CtaFields} from './CtaFields';
import {DetailsStep} from './DetailsStep';
import {IssueList} from './IssueList';
import {LockedView} from './LockedView';
import {Preview} from './Preview';
import {QuestionsStep} from './QuestionsStep';
import {EndBlock} from './RegularEndStep';
import {SuccessScreen} from './SuccessScreen';

/**
 * The generic editor of every other type (PRD §10.7): title, slug (with its …/f/{slug} preview), description, data
 * capture, landing, disclaimer, CTA, questions and "Update". The rest of the flow is saved back as it was.
 */
export function GenericEditor() {
  const {t} = useTranslation('pages.questionnaire-editor');
  const editor = useEditorContext();
  const {draft, update} = editor;
  useDocumentTitle(`Mappi - ${draft.title || t('title')}`);

  if (editor.locked && editor.questionnaireId) {
    return (
      <LockedView
        questionnaire={{
          questionnaire_id: editor.questionnaireId,
          title: draft.title,
          questions: draft.questions.map((q) => ({title: q.title})),
        }}
      />
    );
  }
  if (editor.saved) {
    return <SuccessScreen />;
  }

  const updateButton = (
    <Button
      variant="primary"
      loading={editor.saving}
      disabledReason={editor.primaryDisabledReason}
      onClick={editor.primary}
    >
      {t('actions.update')}
    </Button>
  );

  return (
    <div className="editor">
      <header className="editor__header">
        <nav aria-label={t('breadcrumb.label')} className="editor__breadcrumb">
          <Link to="/questionnaires">{t('breadcrumb.questionnaires')}</Link>
          <span aria-hidden>{'/'}</span>
          <span aria-current="page">{draft.title || t('title')}</span>
        </nav>
        <div className="editor__bar">
          <Badge tone="accent">{t('chip.editing')}</Badge>
          <div className="row editor__actions">
            <Button
              variant="ghost"
              icon={<Icon name="eye" />}
              aria-pressed={editor.previewOpen}
              onClick={editor.togglePreview}
            >
              {editor.previewOpen ? t('actions.hidePreview') : t('actions.preview')}
            </Button>
            <Link className="btn btn--secondary" to="/questionnaires">
              {t('actions.back')}
            </Link>
            {updateButton}
          </div>
        </div>
      </header>
      <div
        className={
          editor.previewOpen ? 'editor__body editor__body--preview' : 'editor__body'
        }
      >
        <main className="editor__main stack">
          <DetailsStep />
          <QuestionsStep />
          <section className="editor__step">
            <h2 className="editor__step-title">{t('generic.end')}</h2>
            <div className="stack">
              <EndBlock
                label={t('end.cta')}
                hint={t('end.ctaHint')}
                checked={draft.ctaOn}
                onChange={(ctaOn) => update({ctaOn})}
              >
                <CtaFields />
              </EndBlock>
              <EndBlock
                label={t('end.capture')}
                hint={t('end.captureHint')}
                checked={draft.captureUserData}
                onChange={(captureUserData) => update({captureUserData})}
              />
            </div>
          </section>
          <IssueList issues={editor.allIssues} />
          <div className="row">{updateButton}</div>
        </main>
        {editor.previewOpen ? <Preview /> : null}
      </div>
    </div>
  );
}
