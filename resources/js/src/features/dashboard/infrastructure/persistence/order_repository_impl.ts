import { OrderRepository } from '../../domain/repository/order_repository';
import { OrderItem, OrderListResponse } from '../../domain/model/order/order_response';
import { OrderFilterRequest } from '../../domain/model/order/order_filter_request';
import { OrderRemoteDataSource } from '../data_source/remote/order_remote_data_source';

export class OrderRepositoryImpl implements OrderRepository {
    constructor(
        private readonly remote: OrderRemoteDataSource = new OrderRemoteDataSource()
    ) {}

    async getOrders(params?: OrderFilterRequest): Promise<OrderListResponse> {
        return this.remote.getOrders(params);
    }

    async getOrderById(id: string | number): Promise<OrderItem> {
        return this.remote.getOrderById(id);
    }

    async resendCallback(id: string | number): Promise<any> {
        return this.remote.resendCallback(id);
    }

    async setSuccess(id: string | number): Promise<any> {
        return this.remote.setSuccess(id);
    }
}
