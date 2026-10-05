import {formatDate, joinClasses} from '@shared/lib';
import {Icon, IconButton, type IconName} from '@shared/ui';
import {type ReactNode, useState} from 'react';
import {useTranslation} from 'react-i18next';
import type {AssignationWizardState} from '../model/useAssignationWizard';

type Item = {
  key: 'questionnaires' | 'organization' | 'assignation';
  /** The colour of its icon, from the stylesheet's pieces. */
  tone: string;
  icon: IconName;
  done: boolean;
  title: string;
  detail: string;
  /** Shown under the piece (the picked questionnaires). */
  more?: ReactNode;
};

/** Picked questionnaires listed before "Show all" folds the rest away. */
const FOLDED = 5;

/** The picked questionnaires by name, each with its × to unpick it; past {@link FOLDED} the rest fold away. */
function PickedList({wizard}: {wizard: AssignationWizardState}) {
  const {t} = useTranslation('pages.assignation-form');
  const [unfolded, setUnfolded] = useState(false);
  const picked = wizard.questionnaires;
  const listed = unfolded ? picked : picked.slice(0, FOLDED);
  return (
    <>
      <ul className="asg-wiz__picked" aria-label={t('summary.pickedLabel')}>
        {listed.map((q) => (
          <li key={q.id}>
            <span title={q.title}>{q.title}</span>
            <IconButton
              size="sm"
              variant="ghost"
              label={t('summary.unpick', {title: q.title})}
              icon={<Icon name="close" size={12} />}
              onClick={() => wizard.unpickQuestionnaires([q.id])}
            />
          </li>
        ))}
      </ul>
      {picked.length > FOLDED ? (
        <button
          type="button"
          className="asg-wiz__link-button"
          aria-expanded={unfolded}
          onClick={() => setUnfolded(!unfolded)}
        >
          {unfolded
            ? t('summary.showLess')
            : t('summary.showAll', {count: picked.length})}
        </button>
      ) : null}
    </>
  );
}

/** "What we're going to create": the questionnaires, the organization and the assignation, pending ones dashed. */
export function WizardSummary({wizard}: {wizard: AssignationWizardState}) {
  const {t, i18n} = useTranslation('pages.assignation-form');
  const {questionnaires, organization, errors} = wizard;
  const pending = (step: number) => t('summary.step', {number: step});

  const items: Item[] = [
    {
      key: 'questionnaires',
      tone: 'questionnaire',
      icon: 'clipboard-list',
      done: errors[0] === null,
      title:
        questionnaires.length > 0
          ? t('summary.questionnaireCount', {count: questionnaires.length})
          : t('summary.questionnairesPending'),
      detail:
        questionnaires.length > 0
          ? t('summary.questions', {
              count: questionnaires.reduce(
                (sum, q) => sum + q.questionCount,
                0,
              ),
            })
          : pending(1),
      more: questionnaires.length > 0 ? <PickedList wizard={wizard} /> : null,
    },
    {
      key: 'organization',
      tone: 'organization',
      icon: 'building-2',
      done: organization !== null,
      title: organization?.name ?? t('summary.notChosen'),
      detail: organization
        ? [
            organization.isNew ? t('summary.new') : null,
            t('summary.members', {count: organization.memberCount}),
          ]
            .filter(Boolean)
            .join(' · ')
        : pending(2),
    },
    {
      key: 'assignation',
      tone: 'project',
      icon: 'list-checks',
      done: errors[2] === null,
      title: wizard.name.trim() || t('summary.assignationPending'),
      detail: wizard.dueDate
        ? t('summary.due', {date: formatDate(wizard.dueDate, i18n.language)})
        : pending(3),
    },
  ];

  return (
    <aside className="asg-wiz__summary" aria-labelledby="asg-wiz-summary">
      <h2 id="asg-wiz-summary" className="asg-wiz__summary-title">
        {t('summary.title')}
      </h2>
      <ul>
        {items.map((item) => (
          <li
            key={item.key}
            className={joinClasses(
              'asg-wiz__piece',
              `asg-wiz__piece--${item.tone}`,
              !item.done && 'asg-wiz__piece--pending',
            )}
          >
            <span className="asg-wiz__piece-icon" aria-hidden>
              <Icon name={item.icon} size={16} />
            </span>
            <div className="asg-wiz__piece-text">
              <span className="asg-wiz__piece-label">
                {t(`summary.${item.key}`)}
              </span>
              <span className="asg-wiz__piece-title" title={item.title}>
                {item.title}
              </span>
              <span className="asg-wiz__piece-detail">{item.detail}</span>
              {item.more}
            </div>
            {item.done ? (
              <span className="asg-wiz__piece-done">
                <Icon name="check" size={16} />
                <span className="visually-hidden">{t('steps.done')}</span>
              </span>
            ) : null}
          </li>
        ))}
      </ul>
      <p className="asg-wiz__note">{t('summary.nothingSaved')}</p>
    </aside>
  );
}
