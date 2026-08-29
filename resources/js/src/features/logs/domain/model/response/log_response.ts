import { LogFile } from './log_file_response';

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
