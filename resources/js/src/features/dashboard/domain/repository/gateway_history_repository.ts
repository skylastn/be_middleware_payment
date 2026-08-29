import { GatewayHistoryFilterRequest, GatewayHistoryPayload } from '../model/gateway_history/gateway_history_model';

export interface GatewayHistoryRepository {
    getGatewayHistory(params: GatewayHistoryFilterRequest): Promise<GatewayHistoryPayload>;
}
