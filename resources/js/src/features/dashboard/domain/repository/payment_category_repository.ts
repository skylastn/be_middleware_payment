import { PaymentCategoryFilterRequest, PaymentCategoryItem, PaymentCategoryListResponse } from '../model/payment/payment_category_model';

export interface PaymentCategoryRepository {
    getPaymentCategories(params?: PaymentCategoryFilterRequest): Promise<PaymentCategoryListResponse>;
    getPaymentCategoryById(id: string | number): Promise<PaymentCategoryItem>;
    createPaymentCategory(data: Record<string, any>): Promise<PaymentCategoryItem>;
    updatePaymentCategory(id: string | number, data: Record<string, any>): Promise<PaymentCategoryItem>;
    deletePaymentCategory(id: string | number): Promise<any>;
}
