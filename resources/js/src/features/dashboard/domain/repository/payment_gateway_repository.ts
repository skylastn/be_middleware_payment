import { PaymentGatewayItem, PaymentGatewayListResponse } from '../model/response/payment/payment_gateway_response';
import { PaymentGatewayFilterRequest } from '../model/request/payment/payment_gateway_filter_request';

export interface PaymentGatewayRepository {
    getPaymentGateways(params?: PaymentGatewayFilterRequest): Promise<PaymentGatewayListResponse>;
    getPaymentGatewayById(id: string | number): Promise<PaymentGatewayItem>;
    createPaymentGateway(data: Record<string, any>): Promise<PaymentGatewayItem>;
    updatePaymentGateway(id: string | number, data: Record<string, any>): Promise<PaymentGatewayItem>;
    deletePaymentGateway(id: string | number): Promise<any>;
}
