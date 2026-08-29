import { apiClient } from '@/shared/network/api_client';
import { GatewayHistoryFilterRequest } from '@/features/dashboard/domain/model/request/gateway_history/gateway_history_filter_request';
import { GatewayHistoryPayload } from '@/features/dashboard/domain/model/response/gateway_history/gateway_history_response';

export class GatewayHistoryRemoteDataSource {
    async getGatewayHistory(params: GatewayHistoryFilterRequest): Promise<GatewayHistoryPayload> {
        const query = new URLSearchParams();
        query.set('payment_repository_id', String(params.payment_repository_id));
        if (params.per_page && params.per_page !== 10) query.set('per_page', String(params.per_page));
        if (params.start_date) query.set('start_date', params.start_date);
        if (params.end_date) query.set('end_date', params.end_date);

        const qs = query.toString();
        const res = await apiClient<any>(`/api/admin/gateway-history${qs ? `?${qs}` : ''}`);
        return res?.data || res;
    }
}
