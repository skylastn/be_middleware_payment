import { GatewayHistoryRepository } from '../../domain/repository/gateway_history_repository';
import { GatewayHistoryFilterRequest } from '../../domain/model/request/gateway_history/gateway_history_filter_request';
import { GatewayHistoryPayload } from '../../domain/model/response/gateway_history/gateway_history_response';
import { GatewayHistoryRemoteDataSource } from '../data_source/remote/gateway_history_remote_data_source';

export class GatewayHistoryRepositoryImpl implements GatewayHistoryRepository {
    constructor(
        private readonly remote: GatewayHistoryRemoteDataSource = new GatewayHistoryRemoteDataSource()
    ) {}

    async getGatewayHistory(params: GatewayHistoryFilterRequest): Promise<GatewayHistoryPayload> {
        return this.remote.getGatewayHistory(params);
    }
}
