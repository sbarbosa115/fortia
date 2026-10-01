import {api, type Schema} from '@shared/api';
import type {DashboardData} from '../model/formulas';

export type Dashboard = Schema<'DashboardOutput'>;
export type DashboardChart = Schema<'DashboardChartOutput'>;

/** The layout; the first request generates it (10–25 s, within the client's 30 s timeout). */
export function fetchDashboard(questionnaireId: string): Promise<Dashboard> {
  return api.get<Dashboard>(`/questionnaire/${questionnaireId}/dashboard`);
}

export function fetchDashboardData(
  questionnaireId: string,
): Promise<DashboardData> {
  return api.get<DashboardData>(
    `/questionnaire/${questionnaireId}/dashboard/data`,
  );
}
