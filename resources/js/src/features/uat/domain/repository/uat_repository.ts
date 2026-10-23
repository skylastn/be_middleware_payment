import { UatRepositoryItem } from '../model/response/uat_repository_response';
import { UatOrderItem } from '../model/response/uat_order_response';
import { UatGenerateQrRequest } from '../model/request/bank_agi/uat_generate_qr_request';
import { UatQueryStatusRequest } from '../model/request/bank_agi/uat_query_status_request';
import { UatNotifyRequest } from '../model/request/bank_agi/uat_notify_request';
import { UatProbeBankRequest } from '../model/request/bank_agi/uat_probe_bank_request';
import {
    UatGenerateQrResponse,
    UatNotifyResponse,
    UatProbeBankResponse,
    UatQueryStatusResponse,
} from '../model/response/bank_agi/uat_bank_agi_response';

export interface UatRepository {
    getRepositories(gateway: string, mode?: string): Promise<UatRepositoryItem[]>;
    listOrders(perPage?: number): Promise<UatOrderItem[]>;
    generateQr(data: UatGenerateQrRequest): Promise<UatGenerateQrResponse>;
    queryStatus(data: UatQueryStatusRequest): Promise<UatQueryStatusResponse>;
    notify(data: UatNotifyRequest): Promise<UatNotifyResponse>;
    probeBank(data: UatProbeBankRequest): Promise<UatProbeBankResponse>;
}
