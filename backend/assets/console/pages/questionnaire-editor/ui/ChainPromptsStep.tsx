import {
  Button,
  Card,
  CardBody,
  Field,
  Icon,
  IconButton,
  Select,
  TextArea,
} from '@shared/ui';
import {useId} from 'react';
import {useTranslation} from 'react-i18next';
import {useEditorContext} from '../model/EditorContext';
import {CHAIN_ENDINGS, type ChainEnding, MAX_PROMPTS} from '../model/types';

/**
 * Step 3 of a Chaining questionnaire (PRD §10.5): the timeline "Starting point" → Prompt N → Questionnaire N, up to
 * 10 prompts, and the ending.
 */
export function ChainPromptsStep() {
  const {t} = useTranslation('pages.questionnaire-editor');
  const headingId = useId();
  const editor = useEditorContext();
  const {draft, update} = editor;
  const full = draft.prompts.length >= MAX_PROMPTS;
  return (
    <section className="editor__step" aria-labelledby={headingId}>
      <h2 id={headingId} className="editor__step-title">
        {t('prompts.title')}
      </h2>
      <p className="muted">{t('prompts.subtitle')}</p>
      <ol className="timeline">
        <li className="timeline__item">
          <span className="timeline__dot" aria-hidden />
          <div>
            <strong>{t('prompts.start')}</strong>
            <p className="muted">{t('prompts.startBody')}</p>
          </div>
        </li>
        {draft.prompts.map((prompt, i) => (
          <li key={prompt.key} className="timeline__group">
            <div className="timeline__item">
              <span className="timeline__dot timeline__dot--accent" aria-hidden />
              <Card className="timeline__card">
                <CardBody>
                  <div className="timeline__card-header">
                    <strong>{t('prompts.prompt', {n: i + 1})}</strong>
                    <IconButton
                      size="sm"
                      label={t('prompts.remove', {n: i + 1})}
                      icon={<Icon name="trash" />}
                      disabled={draft.prompts.length === 1}
                      onClick={() =>
                        update({
                          prompts: draft.prompts.filter((p) => p.key !== prompt.key),
                        })
                      }
                    />
                  </div>
                  <Field label={t('prompts.prompt', {n: i + 1})} required>
                    <TextArea
                      rows={4}
                      placeholder={t('prompts.promptPlaceholder')}
                      value={prompt.text}
                      onChange={(e) =>
                        update({
                          prompts: draft.prompts.map((p) =>
                            p.key === prompt.key ? {...p, text: e.target.value} : p,
                          ),
                        })
                      }
                    />
                  </Field>
                </CardBody>
              </Card>
            </div>
            <div className="timeline__item">
              <span className="timeline__dot" aria-hidden />
              <div>
                <strong>{t('prompts.generated', {n: i + 1})}</strong>
                <p className="muted">{t('prompts.generatedBody', {n: i + 1})}</p>
              </div>
            </div>
          </li>
        ))}
      </ol>
      <div className="row">
        <Button
          icon={<Icon name="plus" />}
          disabledReason={full ? t('prompts.limit', {max: MAX_PROMPTS}) : null}
          onClick={editor.addPrompt}
        >
          {t('prompts.add')}
        </Button>
        <span className="muted">{t('prompts.limit', {max: MAX_PROMPTS})}</span>
      </div>
      <Card>
        <CardBody>
          <Field label={t('prompts.ending')}>
            <Select
              value={draft.ending}
              onChange={(e) => update({ending: e.target.value as ChainEnding})}
              options={CHAIN_ENDINGS.map((ending) => ({
                value: ending,
                label: t(`prompts.endings.${ending}`),
              }))}
            />
          </Field>
        </CardBody>
      </Card>
    </section>
  );
}
