import { PaymentMethodRepository } from '../domain/repository/payment_method_repository';
import { PaymentMethodRepositoryImpl } from '../infrastructure/persistence/payment_method_repository_impl';
import { PaymentMethodFilterRequest } from '../domain/model/request/payment/payment_method_filter_request';
import { PaymentMethodItem, PaymentMethodListResponse } from '../domain/model/response/payment/payment_method_response';

export class PaymentMethodService {
    private repo: PaymentMethodRepository;

    constructor(repo?: PaymentMethodRepository) {
        this.repo = repo ?? new PaymentMethodRepositoryImpl();
    }

    async getPaymentMethods(params?: PaymentMethodFilterRequest): Promise<PaymentMethodListResponse> {
        return this.repo.getPaymentMethods(params);
    }

    async getPaymentMethodById(id: string | number): Promise<PaymentMethodItem> {
        return this.repo.getPaymentMethodById(id);
    }

    async createPaymentMethod(data: Record<string, any>): Promise<PaymentMethodItem> {
        return this.repo.createPaymentMethod(data);
    }

    async updatePaymentMethod(id: string | number, data: Record<string, any>): Promise<PaymentMethodItem> {
        return this.repo.updatePaymentMethod(id, data);
    }

    async deletePaymentMethod(id: string | number): Promise<any> {
        return this.repo.deletePaymentMethod(id);
    }
}

export const paymentMethodService = new PaymentMethodService();
