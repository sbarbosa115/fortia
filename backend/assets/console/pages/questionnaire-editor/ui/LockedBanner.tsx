import {
  QUESTIONNAIRES_QUERY_KEY,
  copyQuestionnaire,
} from '@console/entities/questionnaire';
import {Button, ConfirmDialog, Icon, useToast} from '@shared/ui';
import {useMutation, useQueryClient} from '@tanstack/react-query';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useNavigate} from 'react-router';

/**
 * Above the editor of a questionnaire with answers (PRD §10.7), as in the admin console: "Locked to preserve answers",
 * why, and "Create a copy" (confirmed) that opens the copy in the editor. The editor under it is read-only.
 */
export function LockedBanner({questionnaireId}: {questionnaireId: string}) {
  const {t} = useTranslation('pages.questionnaire-editor');
  const toast = useToast();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [confirming, setConfirming] = useState(false);
  const copy = useMutation({
    mutationFn: () => copyQuestionnaire(questionnaireId),
    onSuccess: (created) => {
      void queryClient.invalidateQueries({queryKey: QUESTIONNAIRES_QUERY_KEY});
      toast.success(t('locked.copied'));
      void navigate(`/questionnaires/${created.questionnaire_id}/edit`);
    },
    onError: (error) => {
      setConfirming(false);
      toast.apiError(error);
    },
  });

  return (
    <div className="locked-banner" role="alert">
      <span className="locked-banner__icon" aria-hidden>
        <Icon name="lock" size={18} />
      </span>
      <div className="locked-banner__text">
        <h2 className="locked-banner__title">{t('locked.title')}</h2>
        <p className="locked-banner__body">{t('locked.body')}</p>
      </div>
      <Button
        className="locked-banner__copy"
        icon={<Icon name="copy" size={16} />}
        loading={copy.isPending}
        onClick={() => setConfirming(true)}
      >
        {t('locked.copy')}
      </Button>
      <ConfirmDialog
        open={confirming}
        title={t('locked.confirmTitle')}
        body={t('locked.confirmBody')}
        confirmLabel={t('locked.confirmYes')}
        loading={copy.isPending}
        onConfirm={() => copy.mutate()}
        onCancel={() => setConfirming(false)}
      />
    </div>
  );
}
