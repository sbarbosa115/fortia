import {type ResultsData, resultsKind} from '../model/results';
import {DiagnosticResults} from './DiagnosticResults';
import {
  DefaultResults,
  EcommerceResults,
  LivingoodResults,
  ProfileResults,
  Samurai8Results,
} from './OtherResults';

/** The results of a submitted session, in the variant its data calls for (PRD §9.12). */
export function ResultsView({data}: {data: ResultsData}) {
  switch (resultsKind(data)) {
    case 'diagnostic':
      return <DiagnosticResults data={data} diagnostic={data.diagnostic!} />;
    case 'ecommerce':
      return <EcommerceResults data={data} products={data.products ?? []} />;
    case 'profile':
      return <ProfileResults data={data} profile={data.profile ?? {}} />;
    case 'samurai8':
      return <Samurai8Results data={data} result={data.extra ?? {}} />;
    case 'livingood':
      return <LivingoodResults data={data} result={data.extra ?? {}} />;
    default:
      return <DefaultResults data={data} />;
  }
}
