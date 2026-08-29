import { PaymentMethodRepository } from '../../domain/repository/payment_method_repository';
import { PaymentMethodFilterRequest, PaymentMethodItem, PaymentMethodListResponse } from '../../domain/model/payment/payment_method_model';
import { PaymentMethodRemoteDataSource } from '../data_source/remote/payment_method_remote_data_source';

export class PaymentMethodRepositoryImpl implements PaymentMethodRepository {
    constructor(
        private readonly remote: PaymentMethodRemoteDataSource = new PaymentMethodRemoteDataSource()
    ) {}

    async getPaymentMethods(params?: PaymentMethodFilterRequest): Promise<PaymentMethodListResponse> {
        return this.remote.getPaymentMethods(params);
    }

    async getPaymentMethodById(id: string | number): Promise<PaymentMethodItem> {
        return this.remote.getPaymentMethodById(id);
    }

    async createPaymentMethod(data: Record<string, any>): Promise<PaymentMethodItem> {
        return this.remote.createPaymentMethod(data);
    }

    async updatePaymentMethod(id: string | number, data: Record<string, any>): Promise<PaymentMethodItem> {
        return this.remote.updatePaymentMethod(id, data);
    }

    async deletePaymentMethod(id: string | number): Promise<any> {
        return this.remote.deletePaymentMethod(id);
    }
}
