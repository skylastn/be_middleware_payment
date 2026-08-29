import { GatewayHistoryRepository } from '../../domain/repository/gateway_history_repository';
import { GatewayHistoryFilterRequest, GatewayHistoryPayload } from '../../domain/model/gateway_history/gateway_history_model';
import { GatewayHistoryRemoteDataSource } from '../data_source/remote/gateway_history_remote_data_source';

export class GatewayHistoryRepositoryImpl implements GatewayHistoryRepository {
    constructor(
        private readonly remote: GatewayHistoryRemoteDataSource = new GatewayHistoryRemoteDataSource()
    ) {}

    async getGatewayHistory(params: GatewayHistoryFilterRequest): Promise<GatewayHistoryPayload> {
        return this.remote.getGatewayHistory(params);
    }
}
