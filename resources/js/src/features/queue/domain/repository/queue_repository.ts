import {
    FailedJobListResponse,
    QueueJobListResponse,
    QueueOverviewResponse,
} from '../model/queue_model';

export interface QueueRepository {
    getOverview(): Promise<QueueOverviewResponse>;
    getActiveJobs(type?: string, page?: number, perPage?: number): Promise<QueueJobListResponse>;
    getFailedJobs(page?: number, perPage?: number, search?: string): Promise<FailedJobListResponse>;
    retryFailedJob(id: string | number): Promise<any>;
    retryAllFailedJobs(): Promise<any>;
    forgetFailedJob(id: string | number): Promise<any>;
    flushFailedJobs(): Promise<any>;
    dispatchTestJob(message: string, delaySeconds?: number): Promise<any>;
}
