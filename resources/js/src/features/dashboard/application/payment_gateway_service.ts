import { PaymentGatewayRepository } from '../domain/repository/payment_gateway_repository';
import { PaymentGatewayRepositoryImpl } from '../infrastructure/persistence/payment_gateway_repository_impl';
import { PaymentGatewayFilterRequest } from '../domain/model/request/payment/payment_gateway_filter_request';
import { PaymentGatewayItem, PaymentGatewayListResponse } from '../domain/model/response/payment/payment_gateway_response';

export class PaymentGatewayService {
    private repo: PaymentGatewayRepository;

    constructor(repo?: PaymentGatewayRepository) {
        this.repo = repo ?? new PaymentGatewayRepositoryImpl();
    }

    async getPaymentGateways(params?: PaymentGatewayFilterRequest): Promise<PaymentGatewayListResponse> {
        return this.repo.getPaymentGateways(params);
    }

    async getPaymentGatewayById(id: string | number): Promise<PaymentGatewayItem> {
        return this.repo.getPaymentGatewayById(id);
    }

    async createPaymentGateway(data: Record<string, any>): Promise<PaymentGatewayItem> {
        return this.repo.createPaymentGateway(data);
    }

    async updatePaymentGateway(id: string | number, data: Record<string, any>): Promise<PaymentGatewayItem> {
        return this.repo.updatePaymentGateway(id, data);
    }

    async deletePaymentGateway(id: string | number): Promise<any> {
        return this.repo.deletePaymentGateway(id);
    }
}

export const paymentGatewayService = new PaymentGatewayService();
