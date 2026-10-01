import {
  QUESTIONNAIRES_QUERY_KEY,
  copyQuestionnaire,
} from '@console/entities/questionnaire';
import {useDocumentTitle} from '@shared/lib';
import {
  Badge,
  Button,
  Card,
  CardBody,
  ConfirmDialog,
  Icon,
  useToast,
} from '@shared/ui';
import {useMutation, useQueryClient} from '@tanstack/react-query';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {Link, useNavigate} from 'react-router';

/**
 * A questionnaire with answers (PRD §10.7): "Locked to preserve answers", and "Create a copy" (confirmed) that
 * opens the copy in the editor.
 */
export function LockedView({
  questionnaire,
}: {
  questionnaire: {
    questionnaire_id: string;
    title: string;
    questions: {title: string}[];
  };
}) {
  const {t} = useTranslation('pages.questionnaire-editor');
  const toast = useToast();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [confirming, setConfirming] = useState(false);
  useDocumentTitle(`Mappi - ${questionnaire.title}`);
  const copy = useMutation({
    mutationFn: () => copyQuestionnaire(questionnaire.questionnaire_id),
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
    <div className="editor editor--locked">
      <nav aria-label={t('breadcrumb.label')} className="editor__breadcrumb">
        <Link to="/questionnaires">{t('breadcrumb.questionnaires')}</Link>
        <span aria-hidden>{'/'}</span>
        <span aria-current="page">{questionnaire.title}</span>
      </nav>
      <Card>
        <CardBody>
          <div className="stack">
            <div className="row">
              <Badge tone="warning">
                <Icon name="lock" size={14} />
                {t('locked.badge')}
              </Badge>
              <h1 className="editor__step-title">{t('locked.title')}</h1>
            </div>
            <p className="muted">{t('locked.body')}</p>
            <h2 className="editor__subheading">{questionnaire.title}</h2>
            <ol className="locked__questions">
              {questionnaire.questions.map((question, i) => (
                <li key={i}>{question.title}</li>
              ))}
            </ol>
            <div className="row">
              <Button
                variant="primary"
                icon={<Icon name="copy" />}
                onClick={() => setConfirming(true)}
              >
                {t('locked.copy')}
              </Button>
              <Link className="btn btn--secondary" to="/questionnaires">
                {t('success.list')}
              </Link>
            </div>
          </div>
        </CardBody>
      </Card>
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
