import {
  DownloadPdfButton,
  type PdfReport,
} from '@respondent/features/download-pdf';
import {Badge, Icon} from '@shared/ui';
import DOMPurify from 'dompurify';
import {useTranslation} from 'react-i18next';
import {
  Legend,
  PolarAngleAxis,
  PolarGrid,
  PolarRadiusAxis,
  Radar,
  RadarChart,
  ResponsiveContainer,
} from 'recharts';
import {
  type Product,
  productPrice,
  type ResultsData,
  scorePercent,
  shortDate,
} from '../model/results';
import {CtaBlock, ResultCard, ScoreBar} from './parts';

/** Product descriptions are HTML from the store: always sanitized before rendering (§9.12, D11). */
export function sanitizeDescription(html: string): string {
  return DOMPurify.sanitize(html, {
    ALLOWED_TAGS: [
      'b',
      'strong',
      'i',
      'em',
      'u',
      'p',
      'br',
      'ul',
      'ol',
      'li',
      'span',
    ],
    ALLOWED_ATTR: [],
  });
}

function ProductCard({product, top}: {product: Product; top: boolean}) {
  const {t} = useTranslation('widgets.results-view');
  const price = productPrice(product.price);
  const description = product.description?.trim()
    ? sanitizeDescription(product.description)
    : '';
  return (
    <article className="product">
      <div className="product__image">
        {product.image_url ? (
          <img src={product.image_url} alt={product.name} loading="lazy" />
        ) : (
          <Icon name="sparkles" size={40} />
        )}
      </div>
      <div className="product__body">
        <div className="row">
          {top ? <Badge tone="accent">{t('ecommerce.topMatch')}</Badge> : null}
          {price ? <span className="product__price">{price}</span> : null}
        </div>
        <h2 className="product__name">{product.name}</h2>
        {description ? (
          <div
            className="product__description"
            dangerouslySetInnerHTML={{__html: description}}
          />
        ) : (
          <p className="product__description muted">
            {t('ecommerce.noDescription')}
          </p>
        )}
        {product.product_url ? (
          <a
            className="btn btn--primary"
            href={product.product_url}
            target="_blank"
            rel="noopener noreferrer"
          >
            {t('ecommerce.viewInStore')}
            <span className="visually-hidden">{t('newTab')}</span>
          </a>
        ) : null}
      </div>
    </article>
  );
}

/** E-commerce: the recommended products in a 1–3 column grid (PRD §9.12). */
export function EcommerceResults({
  data,
  products,
}: {
  data: ResultsData;
  products: Product[];
}) {
  const {t} = useTranslation('widgets.results-view');
  return (
    <div className="results stack">
      <header className="results__hero">
        <h1 className="serif-heading results__title">{t('ecommerce.title')}</h1>
        <p className="muted">{t('ecommerce.subtitle')}</p>
      </header>
      {products.length === 0 ? (
        <ResultCard>
          <p>{t('ecommerce.empty')}</p>
        </ResultCard>
      ) : (
        <div className={`products products--${Math.min(3, products.length)}`}>
          {products.map((product, index) => (
            <ProductCard
              key={product.product_id}
              product={product}
              top={index === 0}
            />
          ))}
        </div>
      )}
      <CtaBlock cta={data.cta} newTabLabel={t('newTab')} />
    </div>
  );
}

/** Default: the answers were sent; result_copy.title/subtitle override the texts (§9.12). */
export function DefaultResults({data}: {data: ResultsData}) {
  const {t} = useTranslation('widgets.results-view');
  return (
    <div className="results stack">
      <ResultCard>
        <div className="results__done">
          <span className="results__done-icon" aria-hidden="true">
            <Icon name="check" size={28} />
          </span>
          <h1 className="serif-heading results__title">
            {data.resultCopy?.['title']?.trim() || t('default.title')}
          </h1>
          <p className="muted">
            {data.resultCopy?.['subtitle']?.trim() || t('default.subtitle')}
          </p>
        </div>
        <CtaBlock cta={data.cta} newTabLabel={t('newTab')} />
      </ResultCard>
    </div>
  );
}

type Dimension = {id: string; score: number; max: number};

function num(value: unknown, fallback = 0): number {
  return typeof value === 'number' && Number.isFinite(value) ? value : fallback;
}

/** AI Team Profile (§9.12): the stage reached, potential and percentile, 5 dimensions, radar, roadmap. */
export function ProfileResults({
  data,
  profile,
}: {
  data: ResultsData;
  profile: Record<string, unknown>;
}) {
  const {t} = useTranslation('widgets.results-view');
  const dimensions = (profile['dimensions'] as Dimension[] | undefined) ?? [];
  if (dimensions.length === 0) {
    return (
      <div className="results stack">
        <ResultCard>
          <p role="alert">{t('profile.missing')}</p>
        </ResultCard>
      </div>
    );
  }
  const score = profile['score'] as {value: number; max: number} | undefined;
  const stage = profile['stage'] as {index: number; id: string} | undefined;
  const stages = (profile['stages'] as string[] | undefined) ?? [];
  const average = (profile['average'] as number[] | undefined) ?? [];
  const top10 = (profile['top10'] as number[] | undefined) ?? [];
  const strengths = (profile['strengths'] as string[] | undefined) ?? [];
  const roadmap =
    (profile['roadmap'] as {days: string; dimension: string}[] | undefined) ??
    [];
  const opportunity = String(profile['opportunity'] ?? '');
  const dimensionName = (id: string) => t(`profile.dimensions.${id}`);
  const radar = dimensions.map((dimension, index) => ({
    name: dimensionName(dimension.id),
    you: dimension.score,
    average: num(average[index]),
    top: num(top10[index]),
  }));

  const report = (): PdfReport => ({
    title: t('profile.eyebrow'),
    subtitle: stage ? t(`profile.stages.${stage.id}`) : undefined,
    sections: [
      {
        kind: 'bars',
        heading: t('profile.dimensionsTitle'),
        rows: dimensions.map((d) => ({
          label: dimensionName(d.id),
          value: `${d.score} / ${d.max}`,
          pct: scorePercent(d.score, d.max),
        })),
      },
      {
        kind: 'list',
        heading: t('profile.roadmapTitle'),
        items: roadmap.map(
          (step) =>
            `${t('profile.days', {days: step.days})} ${t(`profile.roadmap.${step.dimension}`)}`,
        ),
        numbered: false,
      },
    ],
    footer: t('profile.anonymous'),
  });

  return (
    <div className="results stack">
      <header className="results__hero">
        <span className="eyebrow">{t('profile.eyebrow')}</span>
        <h1 className="serif-heading results__title">
          {stage ? t(`profile.stages.${stage.id}`) : ''}
        </h1>
        {score ? (
          <ScoreBar
            label={t('profile.score')}
            pct={scorePercent(score.value, score.max)}
            detail={`${score.value} / ${score.max}`}
          />
        ) : null}
        <ol className="stage-track">
          {stages.map((id, index) => {
            const state =
              stage === undefined || index > stage.index
                ? 'pending'
                : index === stage.index
                  ? 'current'
                  : 'done';
            return (
              <li key={id} className="stage-track__step" data-state={state}>
                <span>{t(`profile.stages.${id}`)}</span>
                <span className="visually-hidden">
                  {t(`profile.stageState.${state}`)}
                </span>
              </li>
            );
          })}
        </ol>
      </header>

      <div className="grid-2">
        <ResultCard title={t('profile.potential')}>
          <p className="results__score">{num(profile['potential'])}%</p>
        </ResultCard>
        <ResultCard title={t('profile.percentile')}>
          <p className="results__score">{num(profile['percentile'])}</p>
        </ResultCard>
      </div>
      <blockquote className="results__quote">{t('profile.quote')}</blockquote>

      <ResultCard title={t('profile.dimensionsTitle')}>
        {dimensions.map((dimension, index) => (
          <ScoreBar
            key={dimension.id}
            label={dimensionName(dimension.id)}
            pct={scorePercent(dimension.score, dimension.max)}
            detail={`${dimension.score} / ${dimension.max}`}
            tone={`var(--chart-${(index % 5) + 1})`}
          />
        ))}
      </ResultCard>

      <ResultCard title={t('profile.radarTitle')}>
        <div
          className="results__radar"
          role="img"
          aria-label={t('profile.radarTitle')}
        >
          <ResponsiveContainer width="100%" height={320}>
            <RadarChart data={radar} outerRadius="68%">
              <PolarGrid />
              <PolarAngleAxis dataKey="name" />
              <PolarRadiusAxis domain={[0, 6]} tick={false} axisLine={false} />
              <Radar
                name={t('profile.you')}
                dataKey="you"
                stroke="var(--color-primary)"
                fill="var(--color-primary)"
                fillOpacity={0.3}
              />
              <Radar
                name={t('profile.average')}
                dataKey="average"
                stroke="#a1a1aa"
                fill="#a1a1aa"
                fillOpacity={0.1}
              />
              <Radar
                name={t('profile.top')}
                dataKey="top"
                stroke="var(--color-accent)"
                fill="var(--color-accent)"
                fillOpacity={0.08}
              />
              <Legend />
            </RadarChart>
          </ResponsiveContainer>
        </div>
      </ResultCard>

      {strengths.length > 0 ? (
        <ResultCard title={t('profile.strengths')}>
          <ul className="results__list">
            {strengths.map((id) => (
              <li key={id}>{dimensionName(id)}</li>
            ))}
          </ul>
        </ResultCard>
      ) : null}
      {opportunity ? (
        <ResultCard title={t('profile.opportunity')}>
          <p className="results__tier">{dimensionName(opportunity)}</p>
          <p>{t(`profile.roadmap.${opportunity}`)}</p>
        </ResultCard>
      ) : null}
      <ResultCard title={t('profile.roadmapTitle')}>
        <ol className="results__list">
          {roadmap.map((step) => (
            <li key={step.days}>
              <strong>{t('profile.days', {days: step.days})}</strong>{' '}
              {t(`profile.roadmap.${step.dimension}`)}
            </li>
          ))}
        </ol>
        <DownloadPdfButton label={t('profile.download')} report={report} />
      </ResultCard>
      <p className="muted results__note">{t('profile.anonymous')}</p>
      <CtaBlock cta={data.cta} newTabLabel={t('newTab')} />
    </div>
  );
}

/** Samurai8 [CLIENT-SPECIFIC] (§9.12): tier, percentile, 5 dimensions out of 6, strengths, quick wins, roadmap. */
export function Samurai8Results({
  data,
  result,
}: {
  data: ResultsData;
  result: Record<string, unknown>;
}) {
  const {t} = useTranslation('widgets.results-view');
  const tier = result['tier'] as {id: string; percentile: number} | undefined;
  const score = result['score'] as {value: number; max: number} | undefined;
  const dimensions = (result['dimensions'] as Dimension[] | undefined) ?? [];
  const strengths = (result['strengths'] as string[] | undefined) ?? [];
  const quickWins = (result['quick_wins'] as string[] | undefined) ?? [];
  const weakest = String(result['weakest'] ?? '');
  const days = num(result['roadmap_days'], 90);
  return (
    <div className="results stack">
      <header className="results__hero">
        <span className="eyebrow">
          {t('samurai8.eyebrow', {date: shortDate(new Date())})}
        </span>
        <h1 className="serif-heading results__title">
          {tier ? t(`samurai8.tiers.${tier.id}`) : ''}
        </h1>
        {tier ? (
          <p className="muted">
            {t('samurai8.percentile', {percentile: tier.percentile})}
          </p>
        ) : null}
        {score ? (
          <ScoreBar
            label={t('samurai8.score')}
            pct={scorePercent(score.value, score.max)}
            detail={`${score.value} / ${score.max}`}
          />
        ) : null}
      </header>
      <ResultCard title={t('samurai8.dimensionsTitle')}>
        {dimensions.map((dimension) => (
          <ScoreBar
            key={dimension.id}
            label={t(`samurai8.dimensions.${dimension.id}`)}
            pct={scorePercent(dimension.score, dimension.max)}
            detail={`${dimension.score} / ${dimension.max}`}
          />
        ))}
      </ResultCard>
      {strengths.length > 0 ? (
        <ResultCard title={t('samurai8.strengths')}>
          <ul className="results__list">
            {strengths.map((id) => (
              <li key={id}>{t(`samurai8.dimensions.${id}`)}</li>
            ))}
          </ul>
        </ResultCard>
      ) : null}
      {weakest ? (
        <ResultCard title={t('samurai8.weakest')}>
          <p>{t(`samurai8.dimensions.${weakest}`)}</p>
        </ResultCard>
      ) : null}
      <ResultCard title={t('samurai8.quickWins')}>
        <ul className="results__list">
          {quickWins.map((id) => (
            <li key={id}>{t(`samurai8.wins.${id}`)}</li>
          ))}
        </ul>
      </ResultCard>
      <ResultCard title={t('samurai8.roadmap', {days})}>
        <p>{t(days === 30 ? 'samurai8.roadmap30' : 'samurai8.roadmap90')}</p>
      </ResultCard>
      <CtaBlock cta={data.cta} newTabLabel={t('newTab')} />
    </div>
  );
}

/** Livingood [CLIENT-SPECIFIC] (§7.7): four scores out of 100, the profile and the action plan. */
export function LivingoodResults({
  data,
  result,
}: {
  data: ResultsData;
  result: Record<string, unknown>;
}) {
  const {t} = useTranslation('widgets.results-view');
  const scores = (result['scores'] as Dimension[] | undefined) ?? [];
  const plan = (result['action_plan'] as string[] | undefined) ?? [];
  const profile = String(result['profile'] ?? '');
  return (
    <div className="results stack">
      <header className="results__hero">
        <span className="eyebrow">{t('livingood.eyebrow')}</span>
        <h1 className="serif-heading results__title">
          {profile ? t(`livingood.areas.${profile}`) : t('livingood.title')}
        </h1>
      </header>
      <ResultCard title={t('livingood.scores')}>
        {scores.map((score) => (
          <ScoreBar
            key={score.id}
            label={t(`livingood.areas.${score.id}`)}
            pct={scorePercent(score.score, score.max)}
            detail={`${score.score} / ${score.max}`}
          />
        ))}
      </ResultCard>
      {plan.length > 0 ? (
        <ResultCard title={t('livingood.plan')}>
          <ol className="results__list">
            {plan.map((id) => (
              <li key={id}>{t(`livingood.actions.${id}`)}</li>
            ))}
          </ol>
        </ResultCard>
      ) : null}
      <CtaBlock cta={data.cta} newTabLabel={t('newTab')} />
    </div>
  );
}
