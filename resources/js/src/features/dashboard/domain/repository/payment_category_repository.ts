import { PaymentCategoryItem, PaymentCategoryListResponse } from '../model/response/payment/payment_category_response';
import { PaymentCategoryFilterRequest } from '../model/request/payment/payment_category_filter_request';

export interface PaymentCategoryRepository {
    getPaymentCategories(params?: PaymentCategoryFilterRequest): Promise<PaymentCategoryListResponse>;
    getPaymentCategoryById(id: string | number): Promise<PaymentCategoryItem>;
    createPaymentCategory(data: Record<string, any>): Promise<PaymentCategoryItem>;
    updatePaymentCategory(id: string | number, data: Record<string, any>): Promise<PaymentCategoryItem>;
    deletePaymentCategory(id: string | number): Promise<any>;
}
