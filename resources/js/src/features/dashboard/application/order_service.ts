import { OrderRepository } from '../domain/repository/order_repository';
import { OrderRepositoryImpl } from '../infrastructure/persistence/order_repository_impl';
import { OrderItem, OrderListResponse } from '../domain/model/order/order_response';
import { OrderFilterRequest } from '../domain/model/order/order_filter_request';

export class OrderService {
    private repo: OrderRepository;

    constructor(repo?: OrderRepository) {
        this.repo = repo ?? new OrderRepositoryImpl();
    }

    async getOrders(params?: OrderFilterRequest): Promise<OrderListResponse> {
        return this.repo.getOrders(params);
    }

    async getOrderById(id: string | number): Promise<OrderItem> {
        return this.repo.getOrderById(id);
    }

    async resendCallback(id: string | number): Promise<any> {
        return this.repo.resendCallback(id);
    }

    async setSuccess(id: string | number): Promise<any> {
        return this.repo.setSuccess(id);
    }
}

export const orderService = new OrderService();
