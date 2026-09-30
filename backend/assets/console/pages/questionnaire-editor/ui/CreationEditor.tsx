import {useDocumentTitle} from '@shared/lib';
import {Badge, Button, ConfirmDialog, Icon} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {Link} from 'react-router';
import {useEditorContext} from '../model/EditorContext';
import {ChainPromptsStep} from './ChainPromptsStep';
import {DetailsStep} from './DetailsStep';
import {DiagnosticResultsStep} from './DiagnosticResultsStep';
import {IssueList} from './IssueList';
import {LockedView} from './LockedView';
import {Preview} from './Preview';
import {QuestionsStep} from './QuestionsStep';
import {RegularEndStep} from './RegularEndStep';
import {Stepper} from './Stepper';
import {SuccessScreen} from './SuccessScreen';

/**
 * The creation container shared by Regular, Diagnostic and Chaining (PRD §10.5): breadcrumb, chip, 3-step stepper,
 * preview toggle, Back and the main action; the step; the live preview.
 */
export function CreationEditor() {
  const {t} = useTranslation('pages.questionnaire-editor');
  const editor = useEditorContext();
  const {draft, mode, step} = editor;
  const kindName = t(`kind.${draft.kind}`);
  useDocumentTitle(
    `Mappi - ${mode === 'create' ? t('breadcrumb.new', {kind: kindName}) : draft.title || t('title')}`,
  );

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

  const primaryLabel =
    mode === 'edit'
      ? t('actions.saveChanges')
      : step < 3
        ? t('actions.continue')
        : t('actions.create');
  const issues = editor.issuesOf(step);

  return (
    <div className="editor">
      <header className="editor__header">
        <nav aria-label={t('breadcrumb.label')} className="editor__breadcrumb">
          <Link to="/questionnaires">{t('breadcrumb.questionnaires')}</Link>
          <span aria-hidden>{'/'}</span>
          <span aria-current="page">
            {mode === 'create'
              ? t('breadcrumb.new', {kind: kindName})
              : draft.title || t('title')}
          </span>
        </nav>
        <div className="editor__bar">
          <Badge tone={mode === 'create' ? 'neutral' : 'accent'}>
            {mode === 'create' ? t('chip.draft') : t('chip.editing')}
          </Badge>
          <Stepper />
          <div className="row editor__actions">
            <Button
              variant="ghost"
              icon={<Icon name="eye" />}
              aria-pressed={editor.previewOpen}
              onClick={editor.togglePreview}
            >
              {editor.previewOpen ? t('actions.hidePreview') : t('actions.preview')}
            </Button>
            {step > 1 ? (
              <Button onClick={editor.back}>{t('actions.back')}</Button>
            ) : (
              <Link
                className="btn btn--secondary"
                to={mode === 'create' ? '/questionnaires/new' : '/questionnaires'}
              >
                {t('actions.back')}
              </Link>
            )}
            <Button
              variant="primary"
              loading={editor.saving}
              disabledReason={editor.primaryDisabledReason}
              onClick={editor.primary}
            >
              {primaryLabel}
            </Button>
          </div>
        </div>
      </header>

      <div
        className={
          editor.previewOpen ? 'editor__body editor__body--preview' : 'editor__body'
        }
      >
        <main className="editor__main">
          {step === 1 ? <DetailsStep /> : null}
          {step === 2 ? <QuestionsStep /> : null}
          {step === 3 && draft.kind === 'regular' ? <RegularEndStep /> : null}
          {step === 3 && draft.kind === 'diagnostic' ? (
            <DiagnosticResultsStep />
          ) : null}
          {step === 3 && draft.kind === 'chaining' ? <ChainPromptsStep /> : null}
          <IssueList issues={issues} />
        </main>
        {editor.previewOpen ? <Preview /> : null}
      </div>

      <ConfirmDialog
        open={editor.confirmOpen}
        title={t('confirm.title')}
        body={t('confirm.body')}
        confirmLabel={t('confirm.yes')}
        loading={editor.saving}
        onConfirm={() => void editor.save()}
        onCancel={editor.closeConfirm}
      />
    </div>
  );
}
