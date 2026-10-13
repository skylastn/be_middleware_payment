import { OrderRepository } from '../domain/repository/order_repository';
import { OrderRepositoryImpl } from '../infrastructure/persistence/order_repository_impl';
import { OrderItem, OrderListResponse } from '../domain/model/response/order/order_response';
import { OrderSummaryTotals } from '../domain/model/response/order/order_summary_response';
import { OrderFilterRequest } from '../domain/model/request/order/order_filter_request';

export class OrderService {
    private repo: OrderRepository;

    constructor(repo?: OrderRepository) {
        this.repo = repo ?? new OrderRepositoryImpl();
    }

    async getOrders(params?: OrderFilterRequest): Promise<OrderListResponse> {
        return this.repo.getOrders(params);
    }

    async getOrderSummary(params?: OrderFilterRequest): Promise<OrderSummaryTotals> {
        return this.repo.getOrderSummary(params);
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
