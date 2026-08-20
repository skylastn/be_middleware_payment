import { api } from '../shared/network/network';
import { DashboardData } from '../model/dashboard_model';

export class DashboardService {
    static async getDashboardData(): Promise<DashboardData> {
        return api<DashboardData>('/api/admin/dashboard');
    }
}
