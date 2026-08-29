export interface ProjectItem {
    id: string | number;
    name: string;
    type: string;
    slug: string;
    callback: string;
    key?: string;
    secure?: string;
    value?: string;
    created_at?: string;
    updated_at?: string;
}

export interface ProjectListResponse {
    status?: boolean;
    code?: number;
    message?: string;
    total?: number;
    perPage?: number;
    currentPage?: number;
    data: ProjectItem[];
}
