import { apiClient } from '@/shared/network/api_client';
import { PaymentMethodFilterRequest } from '@/features/dashboard/domain/model/request/payment/payment_method_filter_request';
import { PaymentMethodItem, PaymentMethodListResponse } from '@/features/dashboard/domain/model/response/payment/payment_method_response';

export class PaymentMethodRemoteDataSource {
    async getPaymentMethods(params?: PaymentMethodFilterRequest): Promise<PaymentMethodListResponse> {
        const query = new URLSearchParams();
        if (params?.page && params.page > 1) query.set('page', String(params.page));
        if (params?.per_page && params.per_page !== 10) query.set('per_page', String(params.per_page));
        if (params?.search?.trim()) query.set('search', params.search.trim());
        if (params?.payment_gateway_id && params.payment_gateway_id !== 'all') query.set('payment_gateway_id', params.payment_gateway_id);
        if (params?.is_active !== undefined && params.is_active !== 'all') query.set('is_active', String(params.is_active));

        const qs = query.toString();
        return apiClient<PaymentMethodListResponse>(`/api/admin/payment-methods${qs ? `?${qs}` : ''}`);
    }

    async getPaymentMethodById(id: string | number): Promise<PaymentMethodItem> {
        const res = await apiClient<any>(`/api/admin/payment-methods/${id}`);
        return res?.data || res;
    }

    async createPaymentMethod(data: Record<string, any>): Promise<PaymentMethodItem> {
        const res = await apiClient<any>('/api/admin/payment-methods/create', {
            method: 'POST',
            body: JSON.stringify(data),
        });
        return res?.data || res;
    }

    async updatePaymentMethod(id: string | number, data: Record<string, any>): Promise<PaymentMethodItem> {
        const res = await apiClient<any>(`/api/admin/payment-methods/${id}`, {
            method: 'PUT',
            body: JSON.stringify(data),
        });
        return res?.data || res;
    }

    async deletePaymentMethod(id: string | number): Promise<any> {
        return apiClient(`/api/admin/payment-methods/${id}`, { method: 'DELETE' });
    }
}
