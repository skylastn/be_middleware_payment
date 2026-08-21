import { LogFilterRequest } from '../model/request/log_filter_request';
import { LogFile } from '../model/response/log_file_response';
import { LogResponse } from '../model/response/log_response';

export interface LogRepository {
    getFiles(): Promise<LogFile[]>;
    getLogs(params?: LogFilterRequest): Promise<LogResponse>;
    clearFileCache(fileIdentifier: string): Promise<any>;
    deleteFile(fileIdentifier: string): Promise<any>;
}
