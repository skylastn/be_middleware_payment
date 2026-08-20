export interface User {
    id?: string | number;
    name?: string;
    email?: string;
    role?: string;
}

export interface ApiResponse<T = any> {
    status?: boolean;
    code?: number;
    message?: string;
    data?: T;
    record?: T;
    perPage?: number;
    total?: number;
    currentPage?: number;
    token?: string;
    user?: User;
    errors?: Record<string, string[]>;
}

export interface PaginationInfo {
    currentPage: number;
    lastPage: number;
    from: number;
    to: number;
    total: number;
    previousPageUrl: string | null;
    nextPageUrl: string | null;
}
