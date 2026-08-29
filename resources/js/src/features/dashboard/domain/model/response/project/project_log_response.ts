export interface ProjectLogItem {
    id: number | string;
    key: string;
    value: any;
    ip: string;
    created_at: string;
    updated_at: string;
}

export interface ProjectLogListResponse {
    status?: boolean;
    code?: number;
    message?: string;
    total: number;
    perPage: number;
    currentPage: number;
    data: ProjectLogItem[];
}
