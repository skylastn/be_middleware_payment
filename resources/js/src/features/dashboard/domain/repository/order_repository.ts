import { OrderItem, OrderListResponse } from '../model/response/order/order_response';
import { OrderSummaryTotals } from '../model/response/order/order_summary_response';
import { OrderFilterRequest } from '../model/request/order/order_filter_request';

export interface OrderRepository {
    getOrders(params?: OrderFilterRequest): Promise<OrderListResponse>;
    getOrderSummary(params?: OrderFilterRequest): Promise<OrderSummaryTotals>;
    getOrderById(id: string | number): Promise<OrderItem>;
    resendCallback(id: string | number): Promise<any>;
    setSuccess(id: string | number): Promise<any>;
}
