import {
    FailedJobListResponse,
    QueueJobListResponse,
    QueueOverviewResponse,
} from '../domain/model/queue_model';
import { QueueRepository } from '../domain/repository/queue_repository';
import { QueueRepositoryImpl } from '../infrastructure/persistence/queue_repository_impl';

export class QueueService {
    constructor(private repo: QueueRepository = new QueueRepositoryImpl()) {}

    async getOverview(): Promise<QueueOverviewResponse> {
        return this.repo.getOverview();
    }

    async getActiveJobs(type = 'all', page = 1, perPage = 20): Promise<QueueJobListResponse> {
        return this.repo.getActiveJobs(type, page, perPage);
    }

    async getFailedJobs(page = 1, perPage = 20, search?: string): Promise<FailedJobListResponse> {
        return this.repo.getFailedJobs(page, perPage, search);
    }

    async retryFailedJob(id: string | number): Promise<any> {
        return this.repo.retryFailedJob(id);
    }

    async retryAllFailedJobs(): Promise<any> {
        return this.repo.retryAllFailedJobs();
    }

    async forgetFailedJob(id: string | number): Promise<any> {
        return this.repo.forgetFailedJob(id);
    }

    async flushFailedJobs(): Promise<any> {
        return this.repo.flushFailedJobs();
    }

    async dispatchTestJob(message: string, delaySeconds = 0): Promise<any> {
        return this.repo.dispatchTestJob(message, delaySeconds);
    }
}

export const queueService = new QueueService();
