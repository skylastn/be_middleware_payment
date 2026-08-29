import { PaymentMethodItem, PaymentMethodListResponse } from '../model/response/payment/payment_method_response';
import { PaymentMethodFilterRequest } from '../model/request/payment/payment_method_filter_request';

export interface PaymentMethodRepository {
    getPaymentMethods(params?: PaymentMethodFilterRequest): Promise<PaymentMethodListResponse>;
    getPaymentMethodById(id: string | number): Promise<PaymentMethodItem>;
    createPaymentMethod(data: Record<string, any>): Promise<PaymentMethodItem>;
    updatePaymentMethod(id: string | number, data: Record<string, any>): Promise<PaymentMethodItem>;
    deletePaymentMethod(id: string | number): Promise<any>;
}
