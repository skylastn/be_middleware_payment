import { PaymentMethodFilterRequest, PaymentMethodItem, PaymentMethodListResponse } from '../model/payment/payment_method_model';

export interface PaymentMethodRepository {
    getPaymentMethods(params?: PaymentMethodFilterRequest): Promise<PaymentMethodListResponse>;
    getPaymentMethodById(id: string | number): Promise<PaymentMethodItem>;
    createPaymentMethod(data: Record<string, any>): Promise<PaymentMethodItem>;
    updatePaymentMethod(id: string | number, data: Record<string, any>): Promise<PaymentMethodItem>;
    deletePaymentMethod(id: string | number): Promise<any>;
}
