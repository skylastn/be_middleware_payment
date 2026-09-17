import { apiClient } from '@/shared/network/api_client';
import {
    FailedJobListResponse,
    QueueJobListResponse,
    QueueOverviewResponse,
} from '@/features/queue/domain/model/queue_model';

export class QueueRemoteDataSource {
    async getOverview(): Promise<QueueOverviewResponse> {
        return apiClient<QueueOverviewResponse>('/api/admin/queue/overview');
    }

    async getActiveJobs(type = 'all', page = 1, perPage = 20): Promise<QueueJobListResponse> {
        const query = new URLSearchParams({
            type,
            page: String(page),
            per_page: String(perPage),
        });
        return apiClient<QueueJobListResponse>(`/api/admin/queue/active-jobs?${query.toString()}`);
    }

    async getFailedJobs(page = 1, perPage = 20, search?: string): Promise<FailedJobListResponse> {
        const query = new URLSearchParams({
            page: String(page),
            per_page: String(perPage),
        });
        if (search) query.set('search', search);

        return apiClient<FailedJobListResponse>(`/api/admin/queue/failed-jobs?${query.toString()}`);
    }

    async retryFailedJob(id: string | number): Promise<any> {
        return apiClient(`/api/admin/queue/failed-jobs/${id}/retry`, {
            method: 'POST',
        });
    }

    async retryAllFailedJobs(): Promise<any> {
        return apiClient('/api/admin/queue/failed-jobs/retry-all', {
            method: 'POST',
        });
    }

    async forgetFailedJob(id: string | number): Promise<any> {
        return apiClient(`/api/admin/queue/failed-jobs/${id}`, {
            method: 'DELETE',
        });
    }

    async flushFailedJobs(): Promise<any> {
        return apiClient('/api/admin/queue/failed-jobs/flush', {
            method: 'DELETE',
        });
    }

    async dispatchTestJob(message: string, delaySeconds = 0): Promise<any> {
        return apiClient('/api/admin/queue/test-dispatch', {
            method: 'POST',
            body: JSON.stringify({
                message,
                delay_seconds: delaySeconds,
            }),
        });
    }
}
