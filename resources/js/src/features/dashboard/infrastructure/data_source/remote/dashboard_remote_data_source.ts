import { apiClient } from '@/shared/network/api_client';
import { DashboardData } from '@/features/dashboard/domain/model/response/dashboard_response';

export interface DashboardFilterParams {
    startDate?: string;
    endDate?: string;
    paymentRepositoryId?: string;
}

export class DashboardRemoteDataSource {
    async getDashboardData(params?: DashboardFilterParams): Promise<DashboardData> {
        const query = new URLSearchParams();
        if (params?.startDate) query.set('start_date', params.startDate);
        if (params?.endDate) query.set('end_date', params.endDate);
        if (params?.paymentRepositoryId && params.paymentRepositoryId !== 'all') {
            query.set('payment_repository_id', params.paymentRepositoryId);
        }

        const qs = query.toString();
        return apiClient<DashboardData>(`/api/admin/dashboard${qs ? `?${qs}` : ''}`);
    }
}
