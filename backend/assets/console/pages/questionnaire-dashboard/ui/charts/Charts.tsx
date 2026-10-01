import {
  distribution,
  gaugeFraction,
  histogram,
  matrix,
  nps,
  rankingAverages,
  scaleOf,
  sliceLimit,
  topAnswer,
  type DashboardChart,
  type DashboardData,
  type DashboardQuestion,
  type NpsResult,
  type QuestionStats,
  type Slice,
} from '@console/entities/dashboard';
import {formatDate} from '@shared/lib';
import {useTranslation} from 'react-i18next';
import {
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  Legend,
  Line,
  LineChart,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  Treemap,
  XAxis,
  YAxis,
} from 'recharts';
import {AXIS, GRID, SINGLE, seriesColor, sequential} from './palette';

export type ChartProps = {
  chart: DashboardChart;
  questions: DashboardQuestion[];
  data: DashboardData;
};

const HEIGHT = 240;
const axisProps = {
  tick: {fill: AXIS, fontSize: 12},
  axisLine: {stroke: GRID},
  tickLine: false,
} as const;

function useChartInputs({chart, questions, data}: ChartProps) {
  const questionId = chart.question_ids[0] ?? '';
  const question = questions.find((q) => q.id === questionId);
  const stats = data.questions.find((s) => s.question_id === questionId);
  return {question, stats};
}

/** Distributions name the tail "Other". */
function useSlices(
  stats: QuestionStats | undefined,
  question: DashboardQuestion | undefined,
  limit: number,
): Slice[] {
  const {t} = useTranslation('pages.questionnaire-dashboard');
  return distribution(stats, question, limit).map((s) =>
    s.other ? {...s, label: t('other')} : s,
  );
}

function NoData() {
  const {t} = useTranslation('pages.questionnaire-dashboard');
  return <p className="muted dash-chart__empty">{t('chart.noData')}</p>;
}

export function DonutChart(props: ChartProps) {
  const {t} = useTranslation('pages.questionnaire-dashboard');
  const {question, stats} = useChartInputs(props);
  const slices = useSlices(stats, question, sliceLimit('donut'));
  const total = slices.reduce((sum, s) => sum + s.count, 0);
  if (total === 0) {
    return <NoData />;
  }
  return (
    <div className="dash-donut">
      <ResponsiveContainer width="100%" height={HEIGHT}>
        <PieChart>
          <Pie
            data={slices}
            dataKey="count"
            nameKey="label"
            innerRadius="58%"
            outerRadius="88%"
            paddingAngle={1}
            stroke="#ffffff"
            strokeWidth={2}
            isAnimationActive={false}
          >
            {slices.map((s, i) => (
              <Cell key={s.key} fill={seriesColor(i, s.other)} />
            ))}
          </Pie>
          <Tooltip />
        </PieChart>
      </ResponsiveContainer>
      <ul className="dash-legend">
        {slices.map((s, i) => (
          <li key={s.key}>
            <span
              className="dash-legend__swatch"
              style={{background: seriesColor(i, s.other)}}
            />
            <span className="dash-legend__label">{s.label}</span>
            <span className="dash-legend__value">
              {t('countPct', {
                count: s.count,
                pct: Math.round((s.count / total) * 100),
              })}
            </span>
          </li>
        ))}
      </ul>
    </div>
  );
}

function CategoryBars({
  slices,
  horizontal,
}: {
  slices: {label: string; count: number; other?: boolean}[];
  horizontal: boolean;
}) {
  const {t} = useTranslation('pages.questionnaire-dashboard');
  if (slices.every((s) => s.count === 0) && slices.length === 0) {
    return <NoData />;
  }
  const height = horizontal
    ? Math.max(HEIGHT, slices.length * 32 + 40)
    : HEIGHT;
  return (
    <ResponsiveContainer width="100%" height={height}>
      <BarChart
        data={slices}
        layout={horizontal ? 'vertical' : 'horizontal'}
        margin={{top: 8, right: 16, bottom: 8, left: 8}}
      >
        <CartesianGrid
          stroke={GRID}
          vertical={horizontal}
          horizontal={!horizontal}
        />
        {horizontal ? (
          <>
            <XAxis type="number" allowDecimals={false} {...axisProps} />
            <YAxis type="category" dataKey="label" width={140} {...axisProps} />
          </>
        ) : (
          <>
            <XAxis dataKey="label" interval={0} {...axisProps} />
            <YAxis allowDecimals={false} width={32} {...axisProps} />
          </>
        )}
        <Tooltip formatter={(value) => [value, t('chart.responses')]} />
        <Bar
          dataKey="count"
          fill={SINGLE}
          radius={horizontal ? [0, 4, 4, 0] : [4, 4, 0, 0]}
          maxBarSize={36}
          isAnimationActive={false}
        >
          {slices.map((s, i) => (
            <Cell key={i} fill={s.other ? seriesColor(0, true) : SINGLE} />
          ))}
        </Bar>
      </BarChart>
    </ResponsiveContainer>
  );
}

export function DistributionBars(props: ChartProps) {
  const {question, stats} = useChartInputs(props);
  const horizontal = props.chart.chart_type === 'horizontal_bar';
  const slices = useSlices(stats, question, sliceLimit(props.chart.chart_type));
  if (slices.length === 0) {
    return <NoData />;
  }
  return <CategoryBars slices={slices} horizontal={horizontal} />;
}

export function TreemapChart(props: ChartProps) {
  const {question, stats} = useChartInputs(props);
  const slices = useSlices(stats, question, sliceLimit('treemap'));
  if (slices.length === 0) {
    return <NoData />;
  }
  const items = slices.map((s, i) => ({
    name: s.label,
    size: s.count,
    fill: seriesColor(i, s.other),
  }));
  return (
    <ResponsiveContainer width="100%" height={HEIGHT}>
      <Treemap
        data={items}
        dataKey="size"
        nameKey="name"
        stroke="#ffffff"
        isAnimationActive={false}
      >
        <Tooltip />
      </Treemap>
    </ResponsiveContainer>
  );
}

export function HistogramChart(props: ChartProps) {
  const {question, stats} = useChartInputs(props);
  const {min, max} = scaleOf(question);
  const bins = histogram(stats, min, max).map((b) => ({
    label: b.value,
    count: b.count,
  }));
  if (bins.length === 0 || (stats?.answers_count ?? 0) === 0) {
    return <NoData />;
  }
  return <CategoryBars slices={bins} horizontal={false} />;
}

function NpsBlock({result}: {result: NpsResult}) {
  const {t} = useTranslation('pages.questionnaire-dashboard');
  if (result.total === 0) {
    return null;
  }
  const parts =
    result.kind === 'nps'
      ? [
          ['detractors', result.detractors],
          ['passives', result.passives],
          ['promoters', result.promoters],
        ]
      : [
          ['low', result.low],
          ['medium', result.medium],
          ['high', result.high],
        ];
  return (
    <div className="dash-nps">
      {result.kind === 'nps' ? (
        <p className="dash-nps__score">
          {t('nps.score', {value: result.score})}
        </p>
      ) : null}
      <ul className="dash-nps__parts">
        {parts.map(([key, count], i) => (
          <li key={key}>
            <span
              className="dash-legend__swatch"
              style={{background: seriesColor([7, 3, 2][i] ?? 0)}}
            />
            {t(`nps.${String(key)}`, {
              count: Number(count),
              pct: Math.round((Number(count) / result.total) * 100),
            })}
          </li>
        ))}
      </ul>
    </div>
  );
}

export function KpiChart(props: ChartProps) {
  const {t} = useTranslation('pages.questionnaire-dashboard');
  const {question, stats} = useChartInputs(props);
  const {min, max} = scaleOf(question);
  if (stats?.numeric) {
    return (
      <div className="dash-kpi">
        <strong className="dash-kpi__value">
          {t('average', {value: stats.numeric.avg.toFixed(1)})}
        </strong>
        {max !== null ? (
          <span className="muted">{t('outOf', {max})}</span>
        ) : null}
        <span className="muted">
          {t('chart.answers', {count: stats.numeric.count})}
        </span>
        <NpsBlock result={nps(stats, min, max)} />
      </div>
    );
  }
  const top = topAnswer(stats, question);
  if (!top) {
    return <NoData />;
  }
  return (
    <div className="dash-kpi">
      <strong className="dash-kpi__value">
        {t('percent', {value: top.pct})}
      </strong>
      <span>{t('chart.topAnswer', {label: top.label})}</span>
    </div>
  );
}

export function GaugeChart(props: ChartProps) {
  const {t} = useTranslation('pages.questionnaire-dashboard');
  const {question, stats} = useChartInputs(props);
  const {min, max} = scaleOf(question);
  const avg = stats?.numeric?.avg;
  if (avg === undefined || min === null || max === null) {
    return <NoData />;
  }
  const fraction = gaugeFraction(avg, min, max);
  const angle = Math.PI * (1 - fraction);
  const x = 100 + 80 * Math.cos(angle);
  const y = 100 - 80 * Math.sin(angle);
  return (
    <div className="dash-gauge">
      <svg
        viewBox="0 0 200 120"
        role="img"
        aria-label={t('chart.gaugeLabel', {value: avg.toFixed(1), min, max})}
      >
        <path
          d="M 20 100 A 80 80 0 0 1 180 100"
          fill="none"
          stroke={GRID}
          strokeWidth={16}
          strokeLinecap="round"
        />
        {fraction > 0 ? (
          <path
            d={`M 20 100 A 80 80 0 0 1 ${x.toFixed(2)} ${y.toFixed(2)}`}
            fill="none"
            stroke={SINGLE}
            strokeWidth={16}
            strokeLinecap="round"
          />
        ) : null}
        <text x="100" y="92" textAnchor="middle" className="dash-gauge__value">
          {avg.toFixed(1)}
        </text>
        <text x="20" y="118" textAnchor="middle" className="dash-gauge__bound">
          {min}
        </text>
        <text x="180" y="118" textAnchor="middle" className="dash-gauge__bound">
          {max}
        </text>
      </svg>
      <NpsBlock result={nps(stats, min, max)} />
    </div>
  );
}

export function BoxplotChart(props: ChartProps) {
  const {t} = useTranslation('pages.questionnaire-dashboard');
  const {question, stats} = useChartInputs(props);
  const numeric = stats?.numeric;
  if (!numeric) {
    return <NoData />;
  }
  const scale = scaleOf(question);
  const low = Math.min(scale.min ?? numeric.min, numeric.min);
  const high = Math.max(scale.max ?? numeric.max, numeric.max);
  const span = high - low || 1;
  const px = (v: number) => 20 + ((v - low) / span) * 260;
  const marks = [
    ['min', numeric.min],
    ['q1', numeric.q1],
    ['median', numeric.median],
    ['q3', numeric.q3],
    ['max', numeric.max],
  ] as const;
  return (
    <div className="dash-boxplot">
      <svg
        viewBox="0 0 300 80"
        role="img"
        aria-label={t('chart.boxplotLabel', numeric)}
      >
        <line
          x1={px(numeric.min)}
          x2={px(numeric.max)}
          y1={40}
          y2={40}
          stroke={AXIS}
          strokeWidth={2}
        />
        <line
          x1={px(numeric.min)}
          x2={px(numeric.min)}
          y1={28}
          y2={52}
          stroke={AXIS}
          strokeWidth={2}
        />
        <line
          x1={px(numeric.max)}
          x2={px(numeric.max)}
          y1={28}
          y2={52}
          stroke={AXIS}
          strokeWidth={2}
        />
        <rect
          x={px(numeric.q1)}
          y={22}
          width={Math.max(2, px(numeric.q3) - px(numeric.q1))}
          height={36}
          rx={4}
          fill={sequential(0.35)}
          stroke={SINGLE}
          strokeWidth={2}
        />
        <line
          x1={px(numeric.median)}
          x2={px(numeric.median)}
          y1={22}
          y2={58}
          stroke={SINGLE}
          strokeWidth={3}
        />
      </svg>
      <dl className="dash-boxplot__values">
        {marks.map(([key, value]) => (
          <div key={key}>
            <dt>{t(`stats.${key}`)}</dt>
            <dd>{Number(value.toFixed(2))}</dd>
          </div>
        ))}
      </dl>
    </div>
  );
}

export function RankingAvgChart({chart, questions, data}: ChartProps) {
  const {t} = useTranslation('pages.questionnaire-dashboard');
  const rows = rankingAverages(
    chart.question_ids,
    questions,
    data.questions,
  ).map((r) => ({label: r.title, avg: Number(r.avg.toFixed(2))}));
  return (
    <ResponsiveContainer
      width="100%"
      height={Math.max(HEIGHT, rows.length * 32 + 40)}
    >
      <BarChart
        data={rows}
        layout="vertical"
        margin={{top: 8, right: 16, bottom: 8, left: 8}}
      >
        <CartesianGrid stroke={GRID} horizontal={false} />
        <XAxis type="number" {...axisProps} />
        <YAxis type="category" dataKey="label" width={160} {...axisProps} />
        <Tooltip formatter={(value) => [value, t('chart.average')]} />
        <Bar
          dataKey="avg"
          fill={SINGLE}
          radius={[0, 4, 4, 0]}
          maxBarSize={28}
          isAnimationActive={false}
        />
      </BarChart>
    </ResponsiveContainer>
  );
}

export function HeatmapChart({chart, questions, data}: ChartProps) {
  const {t} = useTranslation('pages.questionnaire-dashboard');
  const {columns, rows} = matrix(chart.question_ids, questions, data.questions);
  const highest = Math.max(1, ...rows.flatMap((r) => r.counts));
  return (
    <div className="table-wrap">
      <table className="dash-heatmap">
        <caption className="visually-hidden">{chart.title}</caption>
        <thead>
          <tr>
            <th scope="col">{t('chart.question')}</th>
            {columns.map((c) => (
              <th key={c.value} scope="col">
                {c.label}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr key={row.questionId}>
              <th scope="row">{row.title}</th>
              {row.counts.map((count, i) => (
                <td
                  key={i}
                  style={{
                    background: sequential(count / highest),
                    color: count / highest > 0.55 ? '#ffffff' : undefined,
                  }}
                >
                  {count}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

export function StackedBarChart({chart, questions, data}: ChartProps) {
  const {columns, rows} = matrix(chart.question_ids, questions, data.questions);
  const items = rows.map((row) => {
    const item: Record<string, string | number> = {label: row.title};
    columns.forEach((c, i) => {
      item[c.value] = row.counts[i] ?? 0;
    });
    return item;
  });
  return (
    <ResponsiveContainer
      width="100%"
      height={Math.max(HEIGHT, rows.length * 40 + 80)}
    >
      <BarChart
        data={items}
        layout="vertical"
        stackOffset="expand"
        margin={{top: 8, right: 16, bottom: 8, left: 8}}
      >
        <CartesianGrid stroke={GRID} horizontal={false} />
        <XAxis
          type="number"
          tickFormatter={(v: number) => `${Math.round(v * 100)}%`}
          {...axisProps}
        />
        <YAxis type="category" dataKey="label" width={160} {...axisProps} />
        <Tooltip />
        <Legend />
        {columns.map((c, i) => (
          <Bar
            key={c.value}
            dataKey={c.value}
            name={c.label}
            stackId="answers"
            fill={seriesColor(i)}
            stroke="#ffffff"
            strokeWidth={1}
            isAnimationActive={false}
          />
        ))}
      </BarChart>
    </ResponsiveContainer>
  );
}

export function TimelineChart({data}: ChartProps) {
  const {t, i18n} = useTranslation('pages.questionnaire-dashboard');
  const days = data.sessions.timeline.map((d) => ({
    ...d,
    label: formatDate(d.date, i18n.language),
  }));
  if (days.length === 0) {
    return <NoData />;
  }
  return (
    <ResponsiveContainer width="100%" height={HEIGHT}>
      <LineChart data={days} margin={{top: 8, right: 16, bottom: 8, left: 8}}>
        <CartesianGrid stroke={GRID} vertical={false} />
        <XAxis dataKey="label" {...axisProps} />
        <YAxis allowDecimals={false} width={32} {...axisProps} />
        <Tooltip />
        <Legend />
        <Line
          type="monotone"
          dataKey="started"
          name={t('chart.started')}
          stroke={seriesColor(0)}
          strokeWidth={2}
          dot={{r: 4}}
          isAnimationActive={false}
        />
        <Line
          type="monotone"
          dataKey="completed"
          name={t('chart.completed')}
          stroke={seriesColor(1)}
          strokeWidth={2}
          dot={{r: 4}}
          isAnimationActive={false}
        />
      </LineChart>
    </ResponsiveContainer>
  );
}

export function TierChart({data}: ChartProps) {
  const slices = data.tiers.map((tier) => ({
    label: tier.name,
    count: tier.count,
  }));
  if (slices.length === 0) {
    return <NoData />;
  }
  return <CategoryBars slices={slices} horizontal={false} />;
}

/** One chart of the dashboard by its chart_type (the 13 types of PRD §6.19). */
export function DashboardChartBody(props: ChartProps) {
  switch (props.chart.chart_type) {
    case 'kpi':
      return <KpiChart {...props} />;
    case 'gauge':
      return <GaugeChart {...props} />;
    case 'line':
      return <TimelineChart {...props} />;
    case 'donut':
      return <DonutChart {...props} />;
    case 'bar':
    case 'horizontal_bar':
      return <DistributionBars {...props} />;
    case 'treemap':
      return <TreemapChart {...props} />;
    case 'histogram':
      return <HistogramChart {...props} />;
    case 'boxplot':
      return <BoxplotChart {...props} />;
    case 'ranking_avg':
      return <RankingAvgChart {...props} />;
    case 'heatmap':
      return <HeatmapChart {...props} />;
    case 'stacked_bar':
      return <StackedBarChart {...props} />;
    case 'tier_distribution':
      return <TierChart {...props} />;
    default:
      return null;
  }
}
