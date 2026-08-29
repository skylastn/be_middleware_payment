import { ProjectRepository } from '../domain/repository/project_repository';
import { ProjectRepositoryImpl } from '../infrastructure/persistence/project_repository_impl';
import { ProjectFilterRequest, ProjectItem, ProjectListResponse } from '../domain/model/project/project_model';

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
