import { PaymentGatewayRepository } from '../../domain/repository/payment_gateway_repository';
import { PaymentGatewayFilterRequest, PaymentGatewayItem, PaymentGatewayListResponse } from '../../domain/model/payment/payment_gateway_model';
import { PaymentGatewayRemoteDataSource } from '../data_source/remote/payment_gateway_remote_data_source';

export class PaymentGatewayRepositoryImpl implements PaymentGatewayRepository {
    constructor(
        private readonly remote: PaymentGatewayRemoteDataSource = new PaymentGatewayRemoteDataSource()
    ) {}

    async getPaymentGateways(params?: PaymentGatewayFilterRequest): Promise<PaymentGatewayListResponse> {
        return this.remote.getPaymentGateways(params);
    }

    async getPaymentGatewayById(id: string | number): Promise<PaymentGatewayItem> {
        return this.remote.getPaymentGatewayById(id);
    }

    async createPaymentGateway(data: Record<string, any>): Promise<PaymentGatewayItem> {
        return this.remote.createPaymentGateway(data);
    }

    async updatePaymentGateway(id: string | number, data: Record<string, any>): Promise<PaymentGatewayItem> {
        return this.remote.updatePaymentGateway(id, data);
    }

    async deletePaymentGateway(id: string | number): Promise<any> {
        return this.remote.deletePaymentGateway(id);
    }
}
