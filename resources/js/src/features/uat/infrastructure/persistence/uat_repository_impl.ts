import { UatRepository } from '../../domain/repository/uat_repository';
import { UatRepositoryItem } from '../../domain/model/response/uat_repository_response';
import { UatOrderItem } from '../../domain/model/response/uat_order_response';
import { UatGenerateQrRequest } from '../../domain/model/request/bank_agi/uat_generate_qr_request';
import { UatQueryStatusRequest } from '../../domain/model/request/bank_agi/uat_query_status_request';
import { UatNotifyRequest } from '../../domain/model/request/bank_agi/uat_notify_request';
import { UatProbeBankRequest } from '../../domain/model/request/bank_agi/uat_probe_bank_request';
import {
    UatGenerateQrResponse,
    UatNotifyResponse,
    UatProbeBankResponse,
    UatQueryStatusResponse,
} from '../../domain/model/response/bank_agi/uat_bank_agi_response';
import { UatRemoteDataSource } from '../data_source/remote/uat_remote_data_source';

export class UatRepositoryImpl implements UatRepository {
    constructor(
        private readonly remote: UatRemoteDataSource = new UatRemoteDataSource()
    ) {}

    async getRepositories(gateway: string, mode?: string): Promise<UatRepositoryItem[]> {
        return this.remote.getRepositories(gateway, mode);
    }

    async listOrders(perPage?: number): Promise<UatOrderItem[]> {
        return this.remote.listOrders(perPage);
    }

    async generateQr(data: UatGenerateQrRequest): Promise<UatGenerateQrResponse> {
        return this.remote.generateQr(data);
    }

    async queryStatus(data: UatQueryStatusRequest): Promise<UatQueryStatusResponse> {
        return this.remote.queryStatus(data);
    }

    async notify(data: UatNotifyRequest): Promise<UatNotifyResponse> {
        return this.remote.notify(data);
    }

    async probeBank(data: UatProbeBankRequest): Promise<UatProbeBankResponse> {
        return this.remote.probeBank(data);
    }
}
