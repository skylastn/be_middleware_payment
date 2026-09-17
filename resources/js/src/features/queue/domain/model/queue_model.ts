import { BaseResponse } from '@/shared/domain/model/base_response';

export interface QueueStats {
    pending: number;
    scheduled: number;
    reserved: number;
    failed: number;
    total_in_queue: number;
}

export interface QueueOverview {
    driver: string;
    default_queue: string;
    stats: QueueStats;
    details: Record<string, any>;
    timestamp: string;
}

export interface QueueJobItem {
    id: string;
    uuid: string;
    name: string;
    full_name: string;
    queue: string;
    status: 'pending' | 'scheduled' | 'reserved';
    attempts: number;
    max_tries?: number | null;
    timeout?: number | null;
    created_at?: string | null;
    scheduled_for?: string | null;
    raw_payload?: Record<string, any>;
}

export interface FailedJobItem {
    id: string;
    uuid: string;
    connection: string;
    queue: string;
    name: string;
    full_name: string;
    failed_at: string;
    exception_summary: string;
    exception_full: string;
    payload?: Record<string, any>;
}

export interface QueueOverviewResponse extends BaseResponse<QueueOverview> {}

export interface QueueJobListResponse extends BaseResponse<QueueJobItem[]> {
    meta?: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

export interface FailedJobListResponse extends BaseResponse<FailedJobItem[]> {
    meta?: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}
