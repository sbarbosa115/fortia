import {tierOf, type SessionResults} from '@console/entities/answer';
import {formatMoney} from '@shared/lib';
import {Card, CardBody, CardHeader, ProgressBar} from '@shared/ui';
import {useTranslation} from 'react-i18next';

/** The result of a response (PRD §10.8): products, the diagnostic, the AI profile, or "no result". */
export function ResultCard({results}: {results: SessionResults | null}) {
  const {t, i18n} = useTranslation('pages.answer-detail');
  const products = results?.products ?? [];
  const diagnostic = results?.diagnostic ?? null;
  const profile = results?.ai_team_profile ?? null;
  const hasResult =
    products.length > 0 || diagnostic !== null || profile !== null;
  const tier = diagnostic ? tierOf(diagnostic) : null;
  const recommendations =
    diagnostic?.recommendations.filter(
      (r) => r.tier_id === tier?.id && r.recommendation,
    ) ?? [];
  const actions =
    diagnostic?.action_plan.filter((a) => a.tier_id === tier?.id && a.action) ??
    [];

  return (
    <Card>
      <CardHeader title={t('result.title')} />
      <CardBody>
        {!hasResult ? <p className="muted">{t('result.none')}</p> : null}
        {products.length > 0 ? (
          <section className="stack">
            <h3 className="detail-subtitle">{t('result.products')}</h3>
            <ul className="detail-products">
              {products.map((product) => (
                <li key={product.product_id}>
                  {product.product_url ? (
                    <a
                      href={product.product_url}
                      target="_blank"
                      rel="noopener noreferrer"
                    >
                      {product.name}
                    </a>
                  ) : (
                    product.name
                  )}
                  {product.price !== null && product.price !== undefined ? (
                    <span className="muted">
                      {formatMoney(
                        Math.round(product.price * 100),
                        'usd',
                        i18n.language,
                      )}
                    </span>
                  ) : null}
                </li>
              ))}
            </ul>
          </section>
        ) : null}
        {diagnostic ? (
          <section className="stack">
            <h3 className="detail-subtitle">{t('result.diagnostic')}</h3>
            <p className="detail-score">
              {t('result.score', {
                value: diagnostic.score.value,
                max: diagnostic.score.max,
              })}
              {tier ? <strong>{` · ${tier.name}`}</strong> : null}
            </p>
            {diagnostic.categories.map((category) => (
              <div key={category.id} className="detail-category">
                <span>
                  {t('result.category', {
                    name: category.name,
                    score: category.score,
                    max: category.max,
                  })}
                </span>
                <ProgressBar
                  value={category.score}
                  max={category.max || 1}
                  label={category.name}
                />
              </div>
            ))}
            {recommendations.length > 0 ? (
              <>
                <h4 className="detail-subtitle">
                  {t('result.recommendations')}
                </h4>
                <ul>
                  {recommendations.map((r, i) => (
                    <li key={i}>{r.recommendation}</li>
                  ))}
                </ul>
              </>
            ) : null}
            {actions.length > 0 ? (
              <>
                <h4 className="detail-subtitle">{t('result.actionPlan')}</h4>
                <ul>
                  {actions.map((a, i) => (
                    <li key={i}>{a.action}</li>
                  ))}
                </ul>
              </>
            ) : null}
          </section>
        ) : null}
        {profile ? (
          <section className="stack">
            <h3 className="detail-subtitle">{t('result.profile')}</h3>
            <dl className="detail-profile">
              {Object.entries(profile)
                .filter(([, value]) =>
                  ['string', 'number'].includes(typeof value),
                )
                .map(([key, value]) => (
                  <div key={key}>
                    <dt>{key.replace(/_/g, ' ')}</dt>
                    <dd>{String(value)}</dd>
                  </div>
                ))}
            </dl>
          </section>
        ) : null}
      </CardBody>
    </Card>
  );
}
