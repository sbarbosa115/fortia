import {useDocumentTitle} from '@shared/lib';
import {Button, ConfirmDialog, Icon} from '@shared/ui';
import type {ReactNode} from 'react';
import {useTranslation} from 'react-i18next';
import {Link, useNavigate} from 'react-router';
import {useEditorContext} from '../model/EditorContext';
import {useReturnTo} from '../model/returnTo';
import {ChainPromptsStep} from './ChainPromptsStep';
import {DetailsStep} from './DetailsStep';
import {DiagnosticResultsStep} from './DiagnosticResultsStep';
import {IssueList} from './IssueList';
import {LockedBanner} from './LockedBanner';
import {Preview} from './Preview';
import {QuestionOutline} from './QuestionOutline';
import {QuestionsStep} from './QuestionsStep';
import {ElementPool, RegularEndStep} from './RegularEndStep';
import {Stepper} from './Stepper';
import {SuccessScreen} from './SuccessScreen';

/**
 * The creation shell shared by Regular, Diagnostic and Chaining (PRD §10.5), as in the admin console: a header with
 * the breadcrumb, the draft chip, the stepper, the preview toggle, Back and the main action; then the side panel
 * (question outline or elements), the step and the live preview. It fills the viewport; only the panels scroll.
 */
export function CreationEditor() {
  const {t} = useTranslation('pages.questionnaire-editor');
  const navigate = useNavigate();
  const returnTo = useReturnTo();
  const editor = useEditorContext();
  const {draft, mode, step} = editor;
  const kindName = t(`kind.${draft.kind}`);
  const stepName = t(
    `steps.${step === 1 ? 'details' : step === 2 ? 'questions' : draft.kind}`,
  );
  useDocumentTitle(
    `Mappi - ${mode === 'create' ? t('breadcrumb.new', {kind: kindName}) : draft.title || t('title')}`,
  );

  if (editor.saved) {
    return <SuccessScreen />;
  }

  const last = step === 3;
  const primaryLabel =
    mode === 'create' && !last
      ? t('actions.continue')
      : mode === 'edit' && !last
        ? t('actions.continue')
        : mode === 'edit'
          ? t('actions.saveChanges')
          : t('actions.create');
  const primaryIcon = !last ? null : mode === 'edit' ? 'check' : 'plus';
  const side: ReactNode =
    step === 2 ? (
      <QuestionOutline />
    ) : step === 3 && draft.kind === 'regular' ? (
      <ElementPool />
    ) : null;
  const heading = {
    1: {
      title: mode === 'edit' ? t('generic.title') : t('steps.details'),
      subtitle: t('details.subtitle'),
    },
    2: {
      title: t('steps.questions'),
      subtitle:
        draft.kind === 'diagnostic'
          ? t('questions.diagnosticSubtitle')
          : t('questions.subtitle'),
    },
    3: {
      title: t(`steps.${draft.kind}`),
      subtitle: t(`stepSubtitles.${draft.kind}`),
    },
  }[step];
  const goBack = () => {
    if (step > 1) {
      editor.back();
    } else {
      void navigate(
        mode === 'create'
          ? '/questionnaires/new'
          : (returnTo?.to ?? '/questionnaires'),
      );
    }
  };

  return (
    <div className="cshell">
      <header className="cshell__header">
        <div className="cshell__where">
          <nav aria-label={t('breadcrumb.label')} className="cshell__crumbs">
            <Link to="/ai-experience">{t('breadcrumb.workspace')}</Link>
            <span aria-hidden>{'/'}</span>
            <Link to="/questionnaires">{t('breadcrumb.questionnaires')}</Link>
            <span aria-hidden>{'/'}</span>
            <span>{kindName}</span>
            <span aria-hidden>{'/'}</span>
            <span aria-current="page" className="cshell__current">
              {stepName}
            </span>
          </nav>
          <span className="cshell__chip">
            <span className="cshell__chip-dot" aria-hidden />
            {mode === 'create'
              ? t('chip.draft')
              : editor.locked
                ? t('chip.locked')
                : t('chip.editing')}
          </span>
        </div>
        <div className="cshell__stepper">
          <Stepper />
        </div>
        <div className="cshell__actions">
          <button
            type="button"
            className="cshell__eye"
            aria-pressed={editor.previewOpen}
            aria-label={t('actions.preview')}
            title={t('actions.previewHint')}
            onClick={editor.togglePreview}
          >
            <Icon name={editor.previewOpen ? 'eye' : 'eye-off'} size={16} />
          </button>
          <span className="cshell__divider" aria-hidden />
          <Button variant="ghost" className="cshell__back" onClick={goBack}>
            <Icon name="arrow-left" size={16} />
            {t('actions.back')}
          </Button>
          <Button
            variant="primary"
            className="cshell__primary"
            loading={editor.saving}
            icon={
              primaryIcon ? <Icon name={primaryIcon} size={16} /> : undefined
            }
            disabledReason={editor.primaryDisabledReason}
            onClick={editor.primary}
          >
            {primaryLabel}
            {!last ? <Icon name="arrow-right" size={16} /> : null}
          </Button>
        </div>
      </header>

      <div className="cshell__body">
        {side ? (
          <aside className="cshell__side">
            {/* The outline still picks the question to look at (its add and delete are off while locked). */}
            {step === 2 ? (
              side
            ) : (
              <fieldset disabled={editor.locked} className="lock-fieldset">
                {side}
              </fieldset>
            )}
          </aside>
        ) : null}
        <div key={step} className="cshell__main">
          <div className="cshell__column">
            {editor.locked && editor.questionnaireId ? (
              <LockedBanner questionnaireId={editor.questionnaireId} />
            ) : null}
            <div className="step-heading">
              <span className="step-heading__kicker">
                {t('steps.kicker', {n: step, type: kindName})}
              </span>
              <h1 className="step-heading__title">{heading.title}</h1>
              <p className="step-heading__subtitle">{heading.subtitle}</p>
            </div>
            <fieldset disabled={editor.locked} className="lock-fieldset">
              {step === 1 ? <DetailsStep /> : null}
              {step === 2 ? <QuestionsStep /> : null}
              {step === 3 && draft.kind === 'regular' ? (
                <RegularEndStep />
              ) : null}
              {step === 3 && draft.kind === 'diagnostic' ? (
                <DiagnosticResultsStep />
              ) : null}
              {step === 3 && draft.kind === 'chaining' ? (
                <ChainPromptsStep />
              ) : null}
            </fieldset>
            {step === 3 ? <IssueList issues={editor.issuesOf(3)} /> : null}
          </div>
        </div>
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
