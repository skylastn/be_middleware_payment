import { apiClient } from '@/shared/network/api_client';
import { PaymentRepositoryFilterRequest } from '@/features/dashboard/domain/model/request/payment/payment_repository_filter_request';
import { PaymentRepositoryItem, PaymentRepositoryListResponse } from '@/features/dashboard/domain/model/response/payment/payment_repository_response';

export class PaymentRepositoryRemoteDataSource {
    async getPaymentRepositories(params?: PaymentRepositoryFilterRequest): Promise<PaymentRepositoryListResponse> {
        const query = new URLSearchParams();
        if (params?.page && params.page > 1) query.set('page', String(params.page));
        if (params?.per_page && params.per_page !== 10) query.set('per_page', String(params.per_page));
        if (params?.search?.trim()) query.set('search', params.search.trim());
        if (params?.mode && params.mode !== 'all') query.set('mode', params.mode);

        const qs = query.toString();
        return apiClient<PaymentRepositoryListResponse>(`/api/admin/payment-repositories${qs ? `?${qs}` : ''}`);
    }

    async getPaymentRepositoryById(id: string | number): Promise<PaymentRepositoryItem> {
        const res = await apiClient<any>(`/api/admin/payment-repositories/${id}`);
        return res?.data || res;
    }

    async createPaymentRepository(data: Record<string, any>): Promise<PaymentRepositoryItem> {
        const res = await apiClient<any>('/api/admin/payment-repositories/create', {
            method: 'POST',
            body: JSON.stringify(data),
        });
        return res?.data || res;
    }

    async updatePaymentRepository(id: string | number, data: Record<string, any>): Promise<PaymentRepositoryItem> {
        const res = await apiClient<any>(`/api/admin/payment-repositories/${id}`, {
            method: 'PUT',
            body: JSON.stringify(data),
        });
        return res?.data || res;
    }

    async deletePaymentRepository(id: string | number): Promise<any> {
        return apiClient(`/api/admin/payment-repositories/${id}`, { method: 'DELETE' });
    }

    async testOrder(id: string | number, data: Record<string, any> = {}): Promise<any> {
        return apiClient(`/api/admin/payment-repositories/${id}/test-order`, {
            method: 'POST',
            body: JSON.stringify(data),
        });
    }
}
