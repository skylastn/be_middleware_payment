import { GatewayHistoryPayload } from '../model/response/gateway_history/gateway_history_response';
import { GatewayHistoryFilterRequest } from '../model/request/gateway_history/gateway_history_filter_request';

export interface GatewayHistoryRepository {
    getGatewayHistory(params: GatewayHistoryFilterRequest): Promise<GatewayHistoryPayload>;
}
