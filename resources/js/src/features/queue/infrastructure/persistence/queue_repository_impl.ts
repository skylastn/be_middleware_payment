import {
    FailedJobListResponse,
    QueueJobListResponse,
    QueueOverviewResponse,
} from '../../domain/model/queue_model';
import { QueueRepository } from '../../domain/repository/queue_repository';
import { QueueRemoteDataSource } from '../data_source/remote/queue_remote_data_source';

export class QueueRepositoryImpl implements QueueRepository {
    constructor(private remote: QueueRemoteDataSource = new QueueRemoteDataSource()) {}

    async getOverview(): Promise<QueueOverviewResponse> {
        return this.remote.getOverview();
    }

    async getActiveJobs(type = 'all', page = 1, perPage = 20): Promise<QueueJobListResponse> {
        return this.remote.getActiveJobs(type, page, perPage);
    }

    async getFailedJobs(page = 1, perPage = 20, search?: string): Promise<FailedJobListResponse> {
        return this.remote.getFailedJobs(page, perPage, search);
    }

    async retryFailedJob(id: string | number): Promise<any> {
        return this.remote.retryFailedJob(id);
    }

    async retryAllFailedJobs(): Promise<any> {
        return this.remote.retryAllFailedJobs();
    }

    async forgetFailedJob(id: string | number): Promise<any> {
        return this.remote.forgetFailedJob(id);
    }

    async flushFailedJobs(): Promise<any> {
        return this.remote.flushFailedJobs();
    }

    async dispatchTestJob(message: string, delaySeconds = 0): Promise<any> {
        return this.remote.dispatchTestJob(message, delaySeconds);
    }
}
