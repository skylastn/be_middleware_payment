import { apiClient } from '@/shared/network/api_client';
import { ProjectFilterRequest, ProjectItem, ProjectListResponse } from '@/features/dashboard/domain/model/project/project_model';

export class ProjectRemoteDataSource {
    async getProjects(params?: ProjectFilterRequest): Promise<ProjectListResponse> {
        const query = new URLSearchParams();
        if (params?.page && params.page > 1) query.set('page', String(params.page));
        if (params?.per_page && params.per_page !== 10) query.set('per_page', String(params.per_page));
        if (params?.search?.trim()) query.set('search', params.search.trim());
        if (params?.slug && params.slug !== 'all') query.set('slug', params.slug);

        const qs = query.toString();
        return apiClient<ProjectListResponse>(`/api/admin/projects${qs ? `?${qs}` : ''}`);
    }

    async getProjectById(id: string | number): Promise<ProjectItem> {
        const res = await apiClient<any>(`/api/admin/projects/${id}`);
        return res?.data || res;
    }

    async createProject(data: Record<string, any>): Promise<ProjectItem> {
        const res = await apiClient<any>('/api/admin/projects/create', {
            method: 'POST',
            body: JSON.stringify(data),
        });
        return res?.data || res;
    }

    async updateProject(id: string | number, data: Record<string, any>): Promise<ProjectItem> {
        const res = await apiClient<any>(`/api/admin/projects/${id}`, {
            method: 'PUT',
            body: JSON.stringify(data),
        });
        return res?.data || res;
    }

    async deleteProject(id: string | number): Promise<any> {
        return apiClient(`/api/admin/projects/${id}`, { method: 'DELETE' });
    }

    async syncMissingLog(): Promise<any> {
        return apiClient('/api/admin/projects/sync-missing-log', { method: 'POST' });
    }
}
