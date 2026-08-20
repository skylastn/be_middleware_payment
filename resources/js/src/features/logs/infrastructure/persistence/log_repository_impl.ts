import { LogRepository } from '../../domain/repository/log_repository';
import { LogFilterRequest } from '../../domain/model/request/log_filter_request';
import { LogFile } from '../../domain/model/response/log_file_response';
import { LogResponse } from '../../domain/model/response/log_response';
import { LogRemoteDataSource } from '../data_source/remote/log_remote_data_source';

export class LogRepositoryImpl implements LogRepository {
    constructor(
        private readonly remote: LogRemoteDataSource = new LogRemoteDataSource()
    ) {}

    async getFiles(): Promise<LogFile[]> {
        return this.remote.getFiles();
    }

    async getLogs(params?: LogFilterRequest): Promise<LogResponse> {
        return this.remote.getLogs(params);
    }

    async clearFileCache(fileIdentifier: string): Promise<any> {
        return this.remote.clearFileCache(fileIdentifier);
    }

    async deleteFile(fileIdentifier: string): Promise<any> {
        return this.remote.deleteFile(fileIdentifier);
    }
}
