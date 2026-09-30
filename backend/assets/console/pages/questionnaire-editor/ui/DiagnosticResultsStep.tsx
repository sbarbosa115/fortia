import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Field,
  Icon,
  IconButton,
  TextArea,
  TextInput,
} from '@shared/ui';
import {useId} from 'react';
import {useTranslation} from 'react-i18next';
import {useEditorContext} from '../model/EditorContext';
import {maxScores} from '../model/scoring';
import {
  type DraftTier,
  LAYOUT_BLOCKS,
  RESULT_COPY_KEYS,
  TEXT_LIMIT,
} from '../model/types';
import {CtaFields} from './CtaFields';
import {EndBlock} from './RegularEndStep';

/**
 * Step 3 of a Diagnostic, "Results" (PRD §10.5): the top score by area, the tiers (seeded Beginner / Intermediate /
 * Advanced over 0..max), the result blocks with a recommendation and an action per tier, and the results texts.
 */
export function DiagnosticResultsStep() {
  const {t} = useTranslation('pages.questionnaire-editor');
  const headingId = useId();
  const editor = useEditorContext();
  const {draft, update} = editor;
  const scores = maxScores(draft.questions, draft.kind);
  const tierIssue = editor.issuesOf(3).find((i) => i.field === 'tiers');
  const setTier = (key: string, patch: Partial<DraftTier>) =>
    update({
      tiers: draft.tiers.map((tier) =>
        tier.key === key ? {...tier, ...patch} : tier,
      ),
    });
  const tierName = (tier: DraftTier, i: number) =>
    tier.name.trim() || t('results.unnamedTier', {n: i + 1});

  return (
    <section className="editor__step" aria-labelledby={headingId}>
      <h2 id={headingId} className="editor__step-title">
        {t('results.title')}
      </h2>
      <p className="muted">{t('results.subtitle')}</p>
      <div className="stack">
        <Card>
          <CardBody>
            <p className="editor__score-line">
              <strong>{t('results.maxScore', {max: scores.total})}</strong>
              {scores.byCategory.map((c) => (
                <span key={c.category} className="muted">
                  {t('questions.categoryMax', {category: c.category, max: c.max})}
                </span>
              ))}
            </p>
          </CardBody>
        </Card>

        <Card>
          <CardHeader
            title={t('results.tiers')}
            actions={
              <Button size="sm" variant="ghost" onClick={editor.reseedTiers}>
                {t('results.reseed')}
              </Button>
            }
          />
          <CardBody>
            <div className="stack">
              {draft.tiers.map((tier, i) => (
                <fieldset key={tier.key} className="tier">
                  <legend className="field__label">
                    {t('results.tier', {n: i + 1})}
                  </legend>
                  <div className="tier__row">
                    <Field label={t('results.tierName')} required>
                      <TextInput
                        value={tier.name}
                        onChange={(e) => setTier(tier.key, {name: e.target.value})}
                      />
                    </Field>
                    <Field label={t('results.tierFrom')} required>
                      <TextInput
                        inputMode="numeric"
                        value={tier.min}
                        onChange={(e) => setTier(tier.key, {min: e.target.value})}
                      />
                    </Field>
                    <Field label={t('results.tierTo')} required>
                      <TextInput
                        inputMode="numeric"
                        value={tier.max}
                        onChange={(e) => setTier(tier.key, {max: e.target.value})}
                      />
                    </Field>
                    <IconButton
                      size="sm"
                      label={t('results.removeTier', {name: tierName(tier, i)})}
                      icon={<Icon name="trash" />}
                      onClick={() =>
                        update({
                          tiers: draft.tiers.filter((x) => x.key !== tier.key),
                        })
                      }
                    />
                  </div>
                  <Field label={t('results.tierDescription')}>
                    <TextArea
                      rows={2}
                      value={tier.description}
                      onChange={(e) =>
                        setTier(tier.key, {description: e.target.value})
                      }
                    />
                  </Field>
                </fieldset>
              ))}
              {tierIssue ? (
                <p className="field__error" role="alert">
                  {t(tierIssue.key, tierIssue.params)}
                </p>
              ) : null}
              <div>
                <Button
                  size="sm"
                  icon={<Icon name="plus" />}
                  onClick={editor.addTier}
                >
                  {t('results.addTier')}
                </Button>
              </div>
            </div>
          </CardBody>
        </Card>

        <h3 className="editor__subheading">{t('results.blocks')}</h3>
        {LAYOUT_BLOCKS.map((block) => (
          <EndBlock
            key={block}
            label={t(`results.blockNames.${block}`)}
            checked={draft.blocks[block]}
            onChange={(on) => update({blocks: {...draft.blocks, [block]: on}})}
          >
            {block === 'recommendations' || block === 'action_plan' ? (
              <div className="stack">
                {draft.tiers.map((tier, i) => {
                  const source =
                    block === 'recommendations'
                      ? draft.recommendations
                      : draft.actions;
                  return (
                    <Field
                      key={tier.key}
                      label={t(
                        block === 'recommendations'
                          ? 'results.recommendationFor'
                          : 'results.actionFor',
                        {tier: tierName(tier, i)},
                      )}
                    >
                      <TextArea
                        rows={2}
                        value={source[tier.id] ?? ''}
                        onChange={(e) =>
                          update(
                            block === 'recommendations'
                              ? {
                                  recommendations: {
                                    ...draft.recommendations,
                                    [tier.id]: e.target.value,
                                  },
                                }
                              : {
                                  actions: {
                                    ...draft.actions,
                                    [tier.id]: e.target.value,
                                  },
                                },
                          )
                        }
                      />
                    </Field>
                  );
                })}
              </div>
            ) : null}
            {block === 'cta' ? <CtaFields /> : null}
          </EndBlock>
        ))}
        <EndBlock
          label={t('results.blockNames.capture')}
          hint={t('end.captureHint')}
          checked={draft.captureUserData}
          onChange={(captureUserData) => update({captureUserData})}
        />

        <details className="card editor__copy">
          <summary>{t('results.copy')}</summary>
          <p className="muted">{t('results.copyHint')}</p>
          <div className="grid-2">
            {RESULT_COPY_KEYS.map((key) => (
              <Field key={key} label={t(`results.copyKeys.${key}`)}>
                <TextInput
                  maxLength={TEXT_LIMIT}
                  value={draft.resultCopy[key] ?? ''}
                  onChange={(e) =>
                    update({
                      resultCopy: {...draft.resultCopy, [key]: e.target.value},
                    })
                  }
                />
              </Field>
            ))}
          </div>
        </details>
      </div>
    </section>
  );
}
