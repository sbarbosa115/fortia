import {useTranslation} from 'react-i18next';
import type {Issue} from './controls';

/** The text of a validation problem: the questionnaire's own message, or ours (entities.session validation.*). */
export function useIssueText(): (issue: Issue | null) => string | null {
  const {t} = useTranslation('entities.session');
  return (issue) => {
    if (issue === null) {
      return null;
    }
    return 'message' in issue ? issue.message : t(`validation.${issue.key}`);
  };
}
