import { apiClient } from '@/shared/network/api_client';
import { DashboardData } from '@/features/dashboard/domain/model/response/dashboard_response';

export class DashboardRemoteDataSource {
    async getDashboardData(): Promise<DashboardData> {
        return apiClient<DashboardData>('/api/admin/dashboard');
    }
}
