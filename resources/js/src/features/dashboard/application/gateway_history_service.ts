import { GatewayHistoryRepository } from '../domain/repository/gateway_history_repository';
import { GatewayHistoryRepositoryImpl } from '../infrastructure/persistence/gateway_history_repository_impl';
import { GatewayHistoryFilterRequest } from '../domain/model/request/gateway_history/gateway_history_filter_request';
import { GatewayHistoryPayload } from '../domain/model/response/gateway_history/gateway_history_response';

export class GatewayHistoryService {
    private repo: GatewayHistoryRepository;

    constructor(repo?: GatewayHistoryRepository) {
        this.repo = repo ?? new GatewayHistoryRepositoryImpl();
    }

    async getGatewayHistory(params: GatewayHistoryFilterRequest): Promise<GatewayHistoryPayload> {
        return this.repo.getGatewayHistory(params);
    }
}

export const gatewayHistoryService = new GatewayHistoryService();
