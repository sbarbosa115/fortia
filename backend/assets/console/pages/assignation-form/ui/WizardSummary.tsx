import {formatDate, joinClasses} from '@shared/lib';
import {Icon, type IconName} from '@shared/ui';
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
};

/** "What we're going to create": the questionnaires, the organization and the assignation, pending ones dashed. */
export function WizardSummary({wizard}: {wizard: AssignationWizardState}) {
  const {t, i18n} = useTranslation('pages.assignation-form');
  const {questionnaires, organization, errors} = wizard;
  const pending = (step: number) => t('summary.step', {number: step});
  const titles = questionnaires.map((q) => q.title);

  const items: Item[] = [
    {
      key: 'questionnaires',
      tone: 'questionnaire',
      icon: 'clipboard-list',
      done: errors[0] === null,
      title:
        titles.length > 0
          ? titles.join(', ')
          : t('summary.questionnairesPending'),
      detail:
        titles.length > 0
          ? t('summary.questionnaireCount', {count: titles.length})
          : pending(1),
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
            <span className="asg-wiz__piece-text">
              <span className="asg-wiz__piece-label">
                {t(`summary.${item.key}`)}
              </span>
              <span className="asg-wiz__piece-title" title={item.title}>
                {item.title}
              </span>
              <span className="asg-wiz__piece-detail">{item.detail}</span>
            </span>
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
