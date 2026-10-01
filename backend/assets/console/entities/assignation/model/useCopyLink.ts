import {useToast} from '@shared/ui';
import {useTranslation} from 'react-i18next';

/** Copies an assignation's respondent link and says so in a toast ("Copy link", PRD §10.11). */
export function useCopyLink(): (url: string) => void {
  const {t} = useTranslation('entities.assignation');
  const toast = useToast();
  return (url: string) => {
    const done = navigator.clipboard?.writeText(url);
    if (!done) {
      toast.error(t('linkNotCopied'));
      return;
    }
    done.then(
      () => toast.success(t('linkCopied')),
      () => toast.error(t('linkNotCopied')),
    );
  };
}
