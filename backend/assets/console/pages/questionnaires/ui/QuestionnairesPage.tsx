import {useViewer} from '@console/entities/viewer';
import {useDocumentTitle} from '@shared/lib';
import {PageHeader} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {useQuestionnaireListing} from '../model/useQuestionnaireListing';
import {ListingBody} from './ListingBody';
import {ListingToolbar} from './ListingToolbar';
import {NewQuestionnaireAction} from './NewQuestionnaireAction';
import './questionnaires.css';

/** /questionnaires (PRD §10.6): the account's questionnaires, searched, filtered and paginated on the server. */
export function QuestionnairesPage() {
  const {t} = useTranslation('pages.questionnaires');
  const {canWrite} = useViewer();
  const listing = useQuestionnaireListing();
  useDocumentTitle(`Mappi - ${t('title')}`);

  return (
    <div className="questionnaires">
      <PageHeader
        title={t('title')}
        subtitle={
          listing.query.data ? t('count', {count: listing.total}) : undefined
        }
        actions={<NewQuestionnaireAction canWrite={canWrite} />}
      />
      <ListingToolbar listing={listing} />
      <ListingBody listing={listing} canWrite={canWrite} />
    </div>
  );
}
