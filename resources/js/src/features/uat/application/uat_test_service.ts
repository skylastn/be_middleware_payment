import { apiClient } from '@/shared/network/api_client';

export interface UatRepositoryItem {
    id: string;
    key: string;
    mode: string;
    gateway_name: string;
    updated_at: string;
}

export interface UatOrderItem {
    reference: string;
    bill_number: string | null;
    amount: number;
    status: string | null;
    mode: string | null;
    created_at: string;
}

export class UatTestService {
    async getRepositories(gateway: string, mode?: string): Promise<UatRepositoryItem[]> {
        const query = new URLSearchParams();
        if (mode && mode !== 'all') query.set('mode', mode);
        const qs = query.toString();
        const res = await apiClient<any>(`/api/uat/gateways/${gateway}/repositories${qs ? `?${qs}` : ''}`);
        return res?.data || [];
    }

    async generateQr(data: { repositoryId: string; amount: number; expiryPeriod?: number }): Promise<any> {
        return apiClient<any>('/api/uat/bank-agi/generate-qr', {
            method: 'POST',
            body: JSON.stringify(data),
        });
    }

    async queryStatus(reference: string): Promise<any> {
        return apiClient<any>('/api/uat/bank-agi/query-status', {
            method: 'POST',
            body: JSON.stringify({ reference }),
        });
    }

    async notify(data: { reference: string; latestTransactionStatus: string; amount?: number }): Promise<any> {
        return apiClient<any>('/api/uat/bank-agi/notify', {
            method: 'POST',
            body: JSON.stringify(data),
        });
    }

    async listOrders(perPage = 20): Promise<UatOrderItem[]> {
        const res = await apiClient<any>(`/api/uat/orders?per_page=${perPage}`);
        return res?.data || [];
    }

    async negativeTest(data: { case: string; reference?: string }): Promise<any> {
        return apiClient<any>('/api/uat/bank-agi/negative-test', {
            method: 'POST',
            body: JSON.stringify(data),
        });
    }
}

export const uatTestService = new UatTestService();
