import { UatRepository } from '../domain/repository/uat_repository';
import { UatRepositoryImpl } from '../infrastructure/persistence/uat_repository_impl';
import { UatRepositoryItem } from '../domain/model/response/uat_repository_response';
import { UatOrderItem } from '../domain/model/response/uat_order_response';
import { UatGenerateQrRequest } from '../domain/model/request/bank_agi/uat_generate_qr_request';
import { UatQueryStatusRequest } from '../domain/model/request/bank_agi/uat_query_status_request';
import { UatNotifyRequest } from '../domain/model/request/bank_agi/uat_notify_request';
import { UatProbeBankRequest } from '../domain/model/request/bank_agi/uat_probe_bank_request';
import {
    UatGenerateQrResponse,
    UatNotifyResponse,
    UatProbeBankResponse,
    UatQueryStatusResponse,
} from '../domain/model/response/bank_agi/uat_bank_agi_response';

export class UatService {
    private repo: UatRepository;

    constructor(repo?: UatRepository) {
        this.repo = repo ?? new UatRepositoryImpl();
    }

    async getRepositories(gateway: string, mode?: string): Promise<UatRepositoryItem[]> {
        return this.repo.getRepositories(gateway, mode);
    }

    async listOrders(perPage?: number): Promise<UatOrderItem[]> {
        return this.repo.listOrders(perPage);
    }

    async generateQr(data: UatGenerateQrRequest): Promise<UatGenerateQrResponse> {
        return this.repo.generateQr(data);
    }

    async queryStatus(data: UatQueryStatusRequest): Promise<UatQueryStatusResponse> {
        return this.repo.queryStatus(data);
    }

    async notify(data: UatNotifyRequest): Promise<UatNotifyResponse> {
        return this.repo.notify(data);
    }

    async probeBank(data: UatProbeBankRequest): Promise<UatProbeBankResponse> {
        return this.repo.probeBank(data);
    }
}

export const uatService = new UatService();
