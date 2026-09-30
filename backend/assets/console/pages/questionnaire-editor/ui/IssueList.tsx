import {Icon} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import type {Issue} from '../model/validate';

/** What is still missing before the step can continue (each message is the PRD's). */
export function IssueList({issues}: {issues: Issue[]}) {
  const {t} = useTranslation('pages.questionnaire-editor');
  if (issues.length === 0) {
    return null;
  }
  return (
    <ul className="editor__issues" aria-live="polite">
      {issues.map((issue) => (
        <li key={`${issue.key}-${issue.field ?? ''}`}>
          <Icon name="info" size={16} />
          <span>{t(issue.key, issue.params)}</span>
        </li>
      ))}
    </ul>
  );
}
