import {
  DownloadPdfButton,
  type PdfReport,
} from '@respondent/features/download-pdf';
import {appConfig} from '@shared/config';
import {Badge, Icon} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {
  PolarAngleAxis,
  PolarGrid,
  PolarRadiusAxis,
  Radar,
  RadarChart,
  ResponsiveContainer,
} from 'recharts';
import {
  type DiagnosticResult,
  type ResultsData,
  radarPoints,
  scorePercent,
  showsCta,
  textsForTier,
  tierFor,
  visibleSections,
} from '../model/results';
import {CtaBlock, ResultCard, ScoreBar} from './parts';

/** The 15 texts result_copy can override (PRD §6.6). */
export const COPY_KEYS = [
  'eyebrow',
  'title',
  'subtitle',
  'tier_label',
  'overall_score',
  'categories_title',
  'categories_subtitle',
  'chart_title',
  'chart_subtitle',
  'chart_legend',
  'recommendations',
  'action_plan',
  'report_title',
  'report_subtitle',
  'download',
] as const;
type CopyKey = (typeof COPY_KEYS)[number];

function format(value: number): string {
  return Number.isInteger(value) ? String(value) : value.toFixed(1);
}

/**
 * The diagnostic results (PRD §9.12): hero, tier reached, overall score, score by area, radar (≥ 3 areas),
 * recommendations and action plan of the tier (or the nearest lower one), the PDF and the CTA. `layout` limits the
 * blocks and `result_copy` overrides the 15 texts.
 */
export function DiagnosticResults({
  data,
  diagnostic,
}: {
  data: ResultsData;
  diagnostic: DiagnosticResult;
}) {
  const {t} = useTranslation('widgets.results-view');
  const copy = (key: CopyKey) => {
    const custom = data.resultCopy?.[key]?.trim();
    if (custom) {
      return custom;
    }
    if (key === 'title' && data.customerId) {
      const override = appConfig().diagnosticTitleOverrides[data.customerId];
      if (override) {
        return override;
      }
    }
    return t(`diagnostic.${key}`);
  };
  const sections = visibleSections(data.layout, diagnostic.tiers);
  const reached = tierFor(diagnostic.score.value, diagnostic.tiers);
  const recommendations = textsForTier(
    diagnostic.recommendations,
    diagnostic.tiers,
    reached,
  );
  const actions = textsForTier(
    diagnostic.action_plan,
    diagnostic.tiers,
    reached,
  );
  const overall = scorePercent(diagnostic.score.value, diagnostic.score.max);
  const radar = radarPoints(diagnostic.categories);

  const report = (): PdfReport => ({
    title: copy('title'),
    subtitle: copy('subtitle'),
    sections: [
      ...(sections.has('tier') && reached
        ? [
            {
              kind: 'text' as const,
              heading: copy('tier_label'),
              lines: [reached.name, reached.description ?? ''].filter(Boolean),
            },
          ]
        : []),
      ...(sections.has('score')
        ? [
            {
              kind: 'bars' as const,
              heading: copy('overall_score'),
              rows: [
                {
                  label: copy('overall_score'),
                  value: `${format(diagnostic.score.value)} / ${format(diagnostic.score.max)}`,
                  pct: overall,
                },
              ],
            },
          ]
        : []),
      ...(sections.has('categories') && diagnostic.categories.length
        ? [
            {
              kind: 'bars' as const,
              heading: copy('categories_title'),
              rows: diagnostic.categories.map((category) => ({
                label: category.name,
                value: `${scorePercent(category.score, category.max)}% · ${format(category.score)} / ${format(category.max)}`,
                pct: scorePercent(category.score, category.max),
              })),
            },
          ]
        : []),
      ...(sections.has('recommendations') && recommendations.length
        ? [
            {
              kind: 'list' as const,
              heading: copy('recommendations'),
              items: recommendations.map((r) => r.recommendation ?? ''),
              numbered: false,
            },
          ]
        : []),
      ...(sections.has('action_plan') && actions.length
        ? [
            {
              kind: 'list' as const,
              heading: copy('action_plan'),
              items: actions.map((a) => a.action ?? ''),
              numbered: true,
            },
          ]
        : []),
    ],
  });

  return (
    <div className="results stack">
      <header className="results__hero">
        <Badge tone="success">
          <Icon name="check" size={14} /> {copy('eyebrow')}
        </Badge>
        <h1 className="serif-heading results__title">{copy('title')}</h1>
        <p className="muted">{copy('subtitle')}</p>
      </header>

      {sections.has('tier') && reached ? (
        <ResultCard title={copy('tier_label')}>
          <p className="results__tier">{reached.name}</p>
          {reached.description ? <p>{reached.description}</p> : null}
        </ResultCard>
      ) : null}

      {sections.has('score') ? (
        <ResultCard title={copy('overall_score')}>
          <p className="results__score">
            {format(diagnostic.score.value)}
            <span className="muted">
              {' / '}
              {format(diagnostic.score.max)}
            </span>
          </p>
          <ScoreBar
            label={copy('overall_score')}
            pct={overall}
            detail={`${overall}%`}
          />
        </ResultCard>
      ) : null}

      {sections.has('categories') && diagnostic.categories.length > 0 ? (
        <ResultCard
          title={copy('categories_title')}
          subtitle={copy('categories_subtitle')}
        >
          {diagnostic.categories.map((category) => (
            <ScoreBar
              key={category.id}
              label={category.name}
              pct={scorePercent(category.score, category.max)}
              detail={`${scorePercent(category.score, category.max)}% · ${format(category.score)} / ${format(category.max)}`}
            />
          ))}
        </ResultCard>
      ) : null}

      {sections.has('categories') && radar ? (
        <ResultCard
          title={copy('chart_title')}
          subtitle={copy('chart_subtitle')}
        >
          <div
            className="results__radar"
            role="img"
            aria-label={copy('chart_title')}
          >
            <ResponsiveContainer width="100%" height={300}>
              <RadarChart data={radar} outerRadius="70%">
                <PolarGrid />
                <PolarAngleAxis dataKey="name" />
                <PolarRadiusAxis
                  domain={[0, 100]}
                  tick={false}
                  axisLine={false}
                />
                <Radar
                  name={copy('chart_legend')}
                  dataKey="value"
                  stroke="var(--color-primary)"
                  fill="var(--color-primary)"
                  fillOpacity={0.25}
                />
              </RadarChart>
            </ResponsiveContainer>
          </div>
          <p className="results__legend">
            <span className="results__legend-swatch" aria-hidden="true" />
            {copy('chart_legend')}
          </p>
        </ResultCard>
      ) : null}

      {sections.has('recommendations') && recommendations.length > 0 ? (
        <ResultCard title={copy('recommendations')}>
          <ul className="results__list">
            {recommendations.map((item, index) => (
              <li key={index}>{item.recommendation}</li>
            ))}
          </ul>
        </ResultCard>
      ) : null}

      {sections.has('action_plan') && actions.length > 0 ? (
        <ResultCard title={copy('action_plan')}>
          <ol className="results__list">
            {actions.map((item, index) => (
              <li key={index}>{item.action}</li>
            ))}
          </ol>
        </ResultCard>
      ) : null}

      {sections.has('pdf') || (sections.has('cta') && showsCta(data.cta)) ? (
        <ResultCard
          title={sections.has('pdf') ? copy('report_title') : undefined}
          subtitle={sections.has('pdf') ? copy('report_subtitle') : undefined}
        >
          {sections.has('pdf') ? (
            <DownloadPdfButton label={copy('download')} report={report} />
          ) : null}
          {sections.has('cta') ? (
            <CtaBlock cta={data.cta} newTabLabel={t('newTab')} />
          ) : null}
        </ResultCard>
      ) : null}
    </div>
  );
}
