import { PaymentGatewayFilterRequest, PaymentGatewayItem, PaymentGatewayListResponse } from '../model/payment/payment_gateway_model';

export interface PaymentGatewayRepository {
    getPaymentGateways(params?: PaymentGatewayFilterRequest): Promise<PaymentGatewayListResponse>;
    getPaymentGatewayById(id: string | number): Promise<PaymentGatewayItem>;
    createPaymentGateway(data: Record<string, any>): Promise<PaymentGatewayItem>;
    updatePaymentGateway(id: string | number, data: Record<string, any>): Promise<PaymentGatewayItem>;
    deletePaymentGateway(id: string | number): Promise<any>;
}
