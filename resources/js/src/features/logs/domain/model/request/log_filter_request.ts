export interface LogFilterRequest {
    file?: string;
    query?: string;
    exclude_levels?: string[];
    page?: number;
    per_page?: number;
    direction?: 'asc' | 'desc';
}
