import {Badge, Button} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import type {DraftEnding} from '../model/templates';
import {NAMESPACE} from '../model/useOnboarding';

/** Step 4's "Review what the person will receive at the end": the diagnostic's levels or the thank-you screen. */
export function EndingReview({
  ending,
  reviewed,
  onReviewed,
}: {
  ending: DraftEnding;
  reviewed: boolean;
  onReviewed: () => void;
}) {
  const {t} = useTranslation(NAMESPACE);

  return (
    <div className="card onboarding__card stack">
      <h2 className="onboarding__card-title">{t('builder.ending.title')}</h2>
      {ending.kind === 'diagnostic' ? (
        <>
          <p className="muted">{t('builder.ending.diagnostic')}</p>
          <ul className="onboarding__tiers">
            {ending.tiers.map((tier) => (
              <li key={tier.id}>
                <div className="row">
                  <strong>{tier.name}</strong>
                  <Badge>
                    {t('builder.ending.range', {min: tier.min, max: tier.max})}
                  </Badge>
                </div>
                <p>{tier.description}</p>
                <p className="muted">{tier.recommendation}</p>
              </li>
            ))}
          </ul>
        </>
      ) : (
        <>
          <p className="muted">
            {ending.kind === 'process_mapping'
              ? t('builder.ending.processMapping')
              : t('builder.ending.message')}
          </p>
          <blockquote className="onboarding__ending">
            <strong>{ending.title}</strong>
            <p>{ending.message}</p>
          </blockquote>
        </>
      )}
      <div className="row">
        {reviewed ? (
          <Badge tone="success">{t('builder.ending.reviewed')}</Badge>
        ) : (
          <Button size="sm" onClick={onReviewed}>
            {t('builder.ending.review')}
          </Button>
        )}
      </div>
    </div>
  );
}
