import { DashboardData } from '../model/response/dashboard_response';

export interface DashboardRepository {
    getDashboardData(): Promise<DashboardData>;
}
