import { ProjectRepository } from '../../domain/repository/project_repository';
import { ProjectFilterRequest, ProjectItem, ProjectListResponse } from '../../domain/model/project/project_model';
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
