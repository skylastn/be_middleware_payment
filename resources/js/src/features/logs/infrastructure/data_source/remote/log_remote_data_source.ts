import { apiClient } from '@/shared/network/api_client';
import { LogFilterRequest } from '@/features/logs/domain/model/request/log_filter_request';
import { LogFile } from '@/features/logs/domain/model/response/log_file_response';
import { LogResponse } from '@/features/logs/domain/model/response/log_response';

export class LogRemoteDataSource {
    async getFiles(): Promise<LogFile[]> {
        return apiClient<LogFile[]>('/log-viewer/api/files');
    }

    async getLogs(params: LogFilterRequest = {}): Promise<LogResponse> {
        const query = new URLSearchParams();

        if (params.file) query.set('file', params.file);
        if (params.query) query.set('query', params.query);
        if (params.page) query.set('page', String(params.page));
        if (params.per_page) query.set('per_page', String(params.per_page));
        if (params.direction) query.set('direction', params.direction);

        if (params.exclude_levels && params.exclude_levels.length > 0) {
            params.exclude_levels.forEach((lvl) => query.append('exclude_levels[]', lvl));
        }

        const url = `/log-viewer/api/logs?${query.toString()}`;
        return apiClient<LogResponse>(url);
    }

    async clearFileCache(fileIdentifier: string): Promise<any> {
        return apiClient(`/log-viewer/api/files/${encodeURIComponent(fileIdentifier)}/clear-cache`, {
            method: 'POST',
        });
    }

    async deleteFile(fileIdentifier: string): Promise<any> {
        return apiClient(`/log-viewer/api/files/${encodeURIComponent(fileIdentifier)}`, {
            method: 'DELETE',
        });
    }
}
