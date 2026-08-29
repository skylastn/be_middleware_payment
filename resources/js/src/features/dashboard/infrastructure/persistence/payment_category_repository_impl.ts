import { PaymentCategoryRepository } from '../../domain/repository/payment_category_repository';
import { PaymentCategoryFilterRequest, PaymentCategoryItem, PaymentCategoryListResponse } from '../../domain/model/payment/payment_category_model';
import { PaymentCategoryRemoteDataSource } from '../data_source/remote/payment_category_remote_data_source';

export class PaymentCategoryRepositoryImpl implements PaymentCategoryRepository {
    constructor(
        private readonly remote: PaymentCategoryRemoteDataSource = new PaymentCategoryRemoteDataSource()
    ) {}

    async getPaymentCategories(params?: PaymentCategoryFilterRequest): Promise<PaymentCategoryListResponse> {
        return this.remote.getPaymentCategories(params);
    }

    async getPaymentCategoryById(id: string | number): Promise<PaymentCategoryItem> {
        return this.remote.getPaymentCategoryById(id);
    }

    async createPaymentCategory(data: Record<string, any>): Promise<PaymentCategoryItem> {
        return this.remote.createPaymentCategory(data);
    }

    async updatePaymentCategory(id: string | number, data: Record<string, any>): Promise<PaymentCategoryItem> {
        return this.remote.updatePaymentCategory(id, data);
    }

    async deletePaymentCategory(id: string | number): Promise<any> {
        return this.remote.deletePaymentCategory(id);
    }
}
