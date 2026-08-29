import { OrderItem, OrderListResponse } from '../model/order/order_response';
import { OrderFilterRequest } from '../model/order/order_filter_request';

export interface OrderRepository {
    getOrders(params?: OrderFilterRequest): Promise<OrderListResponse>;
    getOrderById(id: string | number): Promise<OrderItem>;
    resendCallback(id: string | number): Promise<any>;
    setSuccess(id: string | number): Promise<any>;
}
