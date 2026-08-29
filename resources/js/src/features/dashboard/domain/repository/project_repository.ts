import { ProjectItem, ProjectListResponse } from '../model/response/project/project_response';
import { ProjectFilterRequest } from '../model/request/project/project_filter_request';
import { ProjectLogListResponse } from '../model/response/project/project_log_response';

export interface ProjectRepository {
    getProjects(params?: ProjectFilterRequest): Promise<ProjectListResponse>;
    getProjectById(id: string | number): Promise<ProjectItem>;
    getProjectLogs(id: string | number, params?: Record<string, any>): Promise<ProjectLogListResponse>;
    getProjectLogKeys(id: string | number): Promise<string[]>;
    clearProjectLogs(id: string | number): Promise<any>;
    createProject(data: Record<string, any>): Promise<ProjectItem>;
    updateProject(id: string | number, data: Record<string, any>): Promise<ProjectItem>;
    deleteProject(id: string | number): Promise<any>;
    syncMissingLog(): Promise<any>;
}
