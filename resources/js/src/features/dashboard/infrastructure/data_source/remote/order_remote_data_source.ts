import { apiClient } from '@/shared/network/api_client';
import { OrderItem, OrderListResponse } from '@/features/dashboard/domain/model/response/order/order_response';
import { OrderFilterRequest } from '@/features/dashboard/domain/model/request/order/order_filter_request';

export class OrderRemoteDataSource {
    async getOrders(params?: OrderFilterRequest): Promise<OrderListResponse> {
        const query = new URLSearchParams();
        if (params?.page && params.page > 1) query.set('page', String(params.page));
        if (params?.per_page && params.per_page !== 10) query.set('per_page', String(params.per_page));
        if (params?.search?.trim()) query.set('search', params.search.trim());
        if (params?.mode && params.mode !== 'all') query.set('mode', params.mode);
        if (params?.status && params.status !== 'all') query.set('status', params.status);
        if (params?.start_date) query.set('start_date', params.start_date);
        if (params?.end_date) query.set('end_date', params.end_date);
        if (params?.payment_repository_id && params.payment_repository_id !== 'all') {
            query.set('payment_repository_id', params.payment_repository_id);
        }

        const qs = query.toString();
        return apiClient<OrderListResponse>(`/api/admin/orders${qs ? `?${qs}` : ''}`);
    }

    async getOrderById(id: string | number): Promise<OrderItem> {
        const res = await apiClient<any>(`/api/admin/orders/${id}`);
        return res?.data || res;
    }

    async resendCallback(id: string | number): Promise<any> {
        return apiClient(`/api/admin/orders/${id}/resend-callback`, { method: 'POST' });
    }

    async setSuccess(id: string | number): Promise<any> {
        return apiClient(`/api/admin/orders/${id}/set-success`, { method: 'POST' });
    }
}
