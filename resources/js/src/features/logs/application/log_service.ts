import { LogRepository } from '../domain/repository/log_repository';
import { LogRepositoryImpl } from '../infrastructure/persistence/log_repository_impl';
import { LogFilterRequest } from '../domain/model/request/log_filter_request';
import { LogFile } from '../domain/model/response/log_file_response';
import { LogResponse } from '../domain/model/response/log_response';

export class LogService {
    private repo: LogRepository;

    constructor(repo?: LogRepository) {
        this.repo = repo ?? new LogRepositoryImpl();
    }

    async getFiles(): Promise<LogFile[]> {
        return this.repo.getFiles();
    }

    async getLogs(params?: LogFilterRequest): Promise<LogResponse> {
        return this.repo.getLogs(params);
    }

    async clearFileCache(fileIdentifier: string): Promise<any> {
        return this.repo.clearFileCache(fileIdentifier);
    }

    async deleteFile(fileIdentifier: string): Promise<any> {
        return this.repo.deleteFile(fileIdentifier);
    }
}

export const logService = new LogService();
