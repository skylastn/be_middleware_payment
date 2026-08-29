import { apiClient } from '@/shared/network/api_client';
import { SettingFilterRequest, SettingItem, SettingListResponse } from '@/features/dashboard/domain/model/setting/setting_model';

export class SettingRemoteDataSource {
    async getSettings(params?: SettingFilterRequest): Promise<SettingListResponse> {
        const query = new URLSearchParams();
        if (params?.page && params.page > 1) query.set('page', String(params.page));
        if (params?.per_page && params.per_page !== 10) query.set('per_page', String(params.per_page));
        if (params?.search?.trim()) query.set('search', params.search.trim());

        const qs = query.toString();
        return apiClient<SettingListResponse>(`/api/admin/settings${qs ? `?${qs}` : ''}`);
    }

    async getSettingById(id: string | number): Promise<SettingItem> {
        const res = await apiClient<any>(`/api/admin/settings/${id}`);
        return res?.data || res;
    }

    async createSetting(data: Record<string, any>): Promise<SettingItem> {
        const res = await apiClient<any>('/api/admin/settings/create', {
            method: 'POST',
            body: JSON.stringify(data),
        });
        return res?.data || res;
    }

    async updateSetting(id: string | number, data: Record<string, any>): Promise<SettingItem> {
        const res = await apiClient<any>(`/api/admin/settings/${id}`, {
            method: 'PUT',
            body: JSON.stringify(data),
        });
        return res?.data || res;
    }

    async deleteSetting(id: string | number): Promise<any> {
        return apiClient(`/api/admin/settings/${id}`, { method: 'DELETE' });
    }
}
