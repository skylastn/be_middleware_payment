import { PaymentCategoryRepository } from '../domain/repository/payment_category_repository';
import { PaymentCategoryRepositoryImpl } from '../infrastructure/persistence/payment_category_repository_impl';
import { PaymentCategoryFilterRequest, PaymentCategoryItem, PaymentCategoryListResponse } from '../domain/model/payment/payment_category_model';

export class PaymentCategoryService {
    private repo: PaymentCategoryRepository;

    constructor(repo?: PaymentCategoryRepository) {
        this.repo = repo ?? new PaymentCategoryRepositoryImpl();
    }

    async getPaymentCategories(params?: PaymentCategoryFilterRequest): Promise<PaymentCategoryListResponse> {
        return this.repo.getPaymentCategories(params);
    }

    async getPaymentCategoryById(id: string | number): Promise<PaymentCategoryItem> {
        return this.repo.getPaymentCategoryById(id);
    }

    async createPaymentCategory(data: Record<string, any>): Promise<PaymentCategoryItem> {
        return this.repo.createPaymentCategory(data);
    }

    async updatePaymentCategory(id: string | number, data: Record<string, any>): Promise<PaymentCategoryItem> {
        return this.repo.updatePaymentCategory(id, data);
    }

    async deletePaymentCategory(id: string | number): Promise<any> {
        return this.repo.deletePaymentCategory(id);
    }
}

export const paymentCategoryService = new PaymentCategoryService();
