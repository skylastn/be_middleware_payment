export interface LogFileType {
    value: string;
    name: string;
}

export interface LogFile {
    identifier: string;
    name: string;
    path: string;
    size: number;
    size_in_mb: number;
    size_formatted: string;
    download_url: string;
    earliest_timestamp?: number;
    latest_timestamp?: number;
    can_download?: boolean;
    can_delete?: boolean;
    type?: LogFileType;
}

export interface LogItem {
    index: number;
    file_identifier: string;
    file_position?: number;
    level: string;
    level_name: string;
    level_class: string;
    datetime: string;
    time: string;
    message: string;
    context?: any;
    extra?: {
        log_size?: number;
        log_size_formatted?: string;
        environment?: string;
    };
    full_text?: string;
    url?: string;
}

export interface LogPagination {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    first_page_url?: string;
    last_page_url?: string;
    next_page_url?: string | null;
    prev_page_url?: string | null;
}

export interface LogLevelCount {
    level: string;
    level_name: string;
    level_class: string;
    count: number;
    selected: boolean;
}

export interface LogResponse {
    file?: LogFile;
    levelCounts?: LogLevelCount[];
    logs: LogItem[];
    pagination: LogPagination;
    percentScanned?: number;
    performance?: {
        memoryUsage?: string;
        requestTime?: string;
        version?: string;
    };
}

export interface LogFilterParams {
    file?: string;
    query?: string;
    exclude_levels?: string[];
    page?: number;
    per_page?: number;
    direction?: 'asc' | 'desc';
}
