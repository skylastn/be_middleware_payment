import { apiClient } from '@/shared/network/api_client';
import { PaymentGatewayFilterRequest } from '@/features/dashboard/domain/model/request/payment/payment_gateway_filter_request';
import { PaymentGatewayItem, PaymentGatewayListResponse } from '@/features/dashboard/domain/model/response/payment/payment_gateway_response';

export class PaymentGatewayRemoteDataSource {
    async getPaymentGateways(params?: PaymentGatewayFilterRequest): Promise<PaymentGatewayListResponse> {
        const query = new URLSearchParams();
        if (params?.page && params.page > 1) query.set('page', String(params.page));
        if (params?.per_page && params.per_page !== 10) query.set('per_page', String(params.per_page));
        if (params?.search?.trim()) query.set('search', params.search.trim());

        const qs = query.toString();
        return apiClient<PaymentGatewayListResponse>(`/api/admin/payment-gateways${qs ? `?${qs}` : ''}`);
    }

    async getPaymentGatewayById(id: string | number): Promise<PaymentGatewayItem> {
        const res = await apiClient<any>(`/api/admin/payment-gateways/${id}`);
        return res?.data || res;
    }

    async createPaymentGateway(data: Record<string, any>): Promise<PaymentGatewayItem> {
        const res = await apiClient<any>('/api/admin/payment-gateways/create', {
            method: 'POST',
            body: JSON.stringify(data),
        });
        return res?.data || res;
    }

    async updatePaymentGateway(id: string | number, data: Record<string, any>): Promise<PaymentGatewayItem> {
        const res = await apiClient<any>(`/api/admin/payment-gateways/${id}`, {
            method: 'PUT',
            body: JSON.stringify(data),
        });
        return res?.data || res;
    }

    async deletePaymentGateway(id: string | number): Promise<any> {
        return apiClient(`/api/admin/payment-gateways/${id}`, { method: 'DELETE' });
    }
}
