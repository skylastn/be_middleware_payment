import { ProjectRepository } from '../../domain/repository/project_repository';
import { ProjectFilterRequest } from '../../domain/model/request/project/project_filter_request';
import { ProjectItem, ProjectListResponse } from '../../domain/model/response/project/project_response';
import { ProjectLogListResponse } from '../../domain/model/response/project/project_log_response';
import { ProjectRemoteDataSource } from '../data_source/remote/project_remote_data_source';

export class ProjectRepositoryImpl implements ProjectRepository {
    constructor(
        private readonly remote: ProjectRemoteDataSource = new ProjectRemoteDataSource()
    ) {}

    async getProjects(params?: ProjectFilterRequest): Promise<ProjectListResponse> {
        return this.remote.getProjects(params);
    }

    async getProjectById(id: string | number): Promise<ProjectItem> {
        return this.remote.getProjectById(id);
    }

    async getProjectLogs(id: string | number, params?: Record<string, any>): Promise<ProjectLogListResponse> {
        return this.remote.getProjectLogs(id, params);
    }

    async getProjectLogKeys(id: string | number): Promise<string[]> {
        return this.remote.getProjectLogKeys(id);
    }

    async clearProjectLogs(id: string | number): Promise<any> {
        return this.remote.clearProjectLogs(id);
    }

    async createProject(data: Record<string, any>): Promise<ProjectItem> {
        return this.remote.createProject(data);
    }

    async updateProject(id: string | number, data: Record<string, any>): Promise<ProjectItem> {
        return this.remote.updateProject(id, data);
    }

    async deleteProject(id: string | number): Promise<any> {
        return this.remote.deleteProject(id);
    }

    async syncMissingLog(): Promise<any> {
        return this.remote.syncMissingLog();
    }
}
