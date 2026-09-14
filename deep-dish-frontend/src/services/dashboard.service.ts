import { httpClient } from './httpClient';

export interface DashboardStats {
  queue_size: number;
  reservations_today: number;
  tables_available: number;
  total_tables: number;
  /** 0-100, calculado no backend; null quando o restaurante não tem mesa. */
  occupancy_percent: number | null;
}

export const dashboardService = {
  async stats(): Promise<DashboardStats> {
    return httpClient.get('/restaurante/dashboard');
  },
};