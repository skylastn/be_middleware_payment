export interface BaseResponse<T = any> {
    status?: boolean;
    code?: number;
    message?: string;
    data?: T;
}

export interface PaginationInfo {
    currentPage: number;
    lastPage: number;
    from: number;
    to: number;
    total: number;
    previousPageUrl?: string | null;
    nextPageUrl?: string | null;
}

export interface PaginatedResponse<T = any> {
    status?: boolean;
    code?: number;
    message?: string;
    total: number;
    perPage: number;
    currentPage: number;
    data: T[];
}
