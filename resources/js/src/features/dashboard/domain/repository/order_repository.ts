import { OrderItem, OrderListResponse } from '../model/response/order/order_response';
import { OrderFilterRequest } from '../model/request/order/order_filter_request';

export interface OrderRepository {
    getOrders(params?: OrderFilterRequest): Promise<OrderListResponse>;
    getOrderById(id: string | number): Promise<OrderItem>;
    resendCallback(id: string | number): Promise<any>;
    setSuccess(id: string | number): Promise<any>;
}
