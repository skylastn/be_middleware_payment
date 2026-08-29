import { apiClient } from '@/shared/network/api_client';
import { PaymentCategoryFilterRequest, PaymentCategoryItem, PaymentCategoryListResponse } from '@/features/dashboard/domain/model/payment/payment_category_model';

export class PaymentCategoryRemoteDataSource {
    async getPaymentCategories(params?: PaymentCategoryFilterRequest): Promise<PaymentCategoryListResponse> {
        const query = new URLSearchParams();
        if (params?.page && params.page > 1) query.set('page', String(params.page));
        if (params?.per_page && params.per_page !== 10) query.set('per_page', String(params.per_page));
        if (params?.search?.trim()) query.set('search', params.search.trim());

        const qs = query.toString();
        return apiClient<PaymentCategoryListResponse>(`/api/admin/payment-categories${qs ? `?${qs}` : ''}`);
    }

    async getPaymentCategoryById(id: string | number): Promise<PaymentCategoryItem> {
        const res = await apiClient<any>(`/api/admin/payment-categories/${id}`);
        return res?.data || res;
    }

    async createPaymentCategory(data: Record<string, any>): Promise<PaymentCategoryItem> {
        const res = await apiClient<any>('/api/admin/payment-categories/create', {
            method: 'POST',
            body: JSON.stringify(data),
        });
        return res?.data || res;
    }

    async updatePaymentCategory(id: string | number, data: Record<string, any>): Promise<PaymentCategoryItem> {
        const res = await apiClient<any>(`/api/admin/payment-categories/${id}`, {
            method: 'PUT',
            body: JSON.stringify(data),
        });
        return res?.data || res;
    }

    async deletePaymentCategory(id: string | number): Promise<any> {
        return apiClient(`/api/admin/payment-categories/${id}`, { method: 'DELETE' });
    }
}
