import { ProjectRepository } from '../domain/repository/project_repository';
import { ProjectRepositoryImpl } from '../infrastructure/persistence/project_repository_impl';
import { ProjectFilterRequest } from '../domain/model/request/project/project_filter_request';
import { ProjectItem, ProjectListResponse } from '../domain/model/response/project/project_response';
import { ProjectLogListResponse } from '../domain/model/response/project/project_log_response';

export class ProjectService {
    private repo: ProjectRepository;

    constructor(repo?: ProjectRepository) {
        this.repo = repo ?? new ProjectRepositoryImpl();
    }

    async getProjects(params?: ProjectFilterRequest): Promise<ProjectListResponse> {
        return this.repo.getProjects(params);
    }

    async getProjectById(id: string | number): Promise<ProjectItem> {
        return this.repo.getProjectById(id);
    }

    async getProjectLogs(id: string | number, params?: Record<string, any>): Promise<ProjectLogListResponse> {
        return this.repo.getProjectLogs(id, params);
    }

    async getProjectLogKeys(id: string | number): Promise<string[]> {
        return this.repo.getProjectLogKeys(id);
    }

    async clearProjectLogs(id: string | number): Promise<any> {
        return this.repo.clearProjectLogs(id);
    }

    async createProject(data: Record<string, any>): Promise<ProjectItem> {
        return this.repo.createProject(data);
    }

    async updateProject(id: string | number, data: Record<string, any>): Promise<ProjectItem> {
        return this.repo.updateProject(id, data);
    }

    async deleteProject(id: string | number): Promise<any> {
        return this.repo.deleteProject(id);
    }

    async syncMissingLog(): Promise<any> {
        return this.repo.syncMissingLog();
    }
}

export const projectService = new ProjectService();
