import { ProjectItem, ProjectListResponse } from '../model/response/project/project_response';
import { ProjectFilterRequest } from '../model/request/project/project_filter_request';

export interface ProjectRepository {
    getProjects(params?: ProjectFilterRequest): Promise<ProjectListResponse>;
    getProjectById(id: string | number): Promise<ProjectItem>;
    createProject(data: Record<string, any>): Promise<ProjectItem>;
    updateProject(id: string | number, data: Record<string, any>): Promise<ProjectItem>;
    deleteProject(id: string | number): Promise<any>;
    syncMissingLog(): Promise<any>;
}
