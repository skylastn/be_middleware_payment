import { apiClient } from '@/shared/network/api_client';
import { UatRepositoryItem } from '@/features/uat/domain/model/response/uat_repository_response';
import { UatOrderItem } from '@/features/uat/domain/model/response/uat_order_response';
import { UatGenerateQrRequest } from '@/features/uat/domain/model/request/bank_agi/uat_generate_qr_request';
import { UatQueryStatusRequest } from '@/features/uat/domain/model/request/bank_agi/uat_query_status_request';
import { UatNotifyRequest } from '@/features/uat/domain/model/request/bank_agi/uat_notify_request';
import { UatProbeBankRequest } from '@/features/uat/domain/model/request/bank_agi/uat_probe_bank_request';
import {
    UatGenerateQrResponse,
    UatNotifyResponse,
    UatProbeBankResponse,
    UatQueryStatusResponse,
} from '@/features/uat/domain/model/response/bank_agi/uat_bank_agi_response';

export class UatRemoteDataSource {
    async getRepositories(gateway: string, mode?: string): Promise<UatRepositoryItem[]> {
        const query = new URLSearchParams();
        if (mode && mode !== 'all') query.set('mode', mode);
        const qs = query.toString();
        const res = await apiClient<any>(`/api/uat/gateways/${gateway}/repositories${qs ? `?${qs}` : ''}`);
        return res?.data || [];
    }

    async listOrders(perPage = 20): Promise<UatOrderItem[]> {
        const res = await apiClient<any>(`/api/uat/orders?per_page=${perPage}`);
        return res?.data || [];
    }

    async generateQr(data: UatGenerateQrRequest): Promise<UatGenerateQrResponse> {
        const res = await apiClient<any>('/api/uat/bank-agi/generate-qr', {
            method: 'POST',
            body: JSON.stringify(data),
        });
        return res?.data || res;
    }

    async queryStatus(data: UatQueryStatusRequest): Promise<UatQueryStatusResponse> {
        const res = await apiClient<any>('/api/uat/bank-agi/query-status', {
            method: 'POST',
            body: JSON.stringify(data),
        });
        return res?.data || res;
    }

    async notify(data: UatNotifyRequest): Promise<UatNotifyResponse> {
        const res = await apiClient<any>('/api/uat/bank-agi/notify', {
            method: 'POST',
            body: JSON.stringify(data),
        });
        return res?.data || res;
    }

    async probeBank(data: UatProbeBankRequest): Promise<UatProbeBankResponse> {
        const res = await apiClient<any>('/api/uat/bank-agi/probe', {
            method: 'POST',
            body: JSON.stringify(data),
        });
        return res?.data || res;
    }
}
