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
