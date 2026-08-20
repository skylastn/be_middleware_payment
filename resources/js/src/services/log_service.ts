import { api } from '../shared/network/network';
import { LogFile, LogFilterParams, LogResponse } from '../model/log_model';

export class LogService {
    static async getFiles(): Promise<LogFile[]> {
        return api<LogFile[]>('/log-viewer/api/files');
    }

    static async getLogs(params: LogFilterParams = {}): Promise<LogResponse> {
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
        return api<LogResponse>(url);
    }

    static async clearFileCache(fileIdentifier: string): Promise<any> {
        return api(`/log-viewer/api/files/${encodeURIComponent(fileIdentifier)}/clear-cache`, {
            method: 'POST',
        });
    }

    static async deleteFile(fileIdentifier: string): Promise<any> {
        return api(`/log-viewer/api/files/${encodeURIComponent(fileIdentifier)}`, {
            method: 'DELETE',
        });
    }
}
