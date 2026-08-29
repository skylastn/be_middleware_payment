import { PaymentRepositoryItem, PaymentRepositoryListResponse } from '../model/response/payment/payment_repository_response';
import { PaymentRepositoryFilterRequest } from '../model/request/payment/payment_repository_filter_request';

export interface PaymentRepositoryRepository {
    getPaymentRepositories(params?: PaymentRepositoryFilterRequest): Promise<PaymentRepositoryListResponse>;
    getPaymentRepositoryById(id: string | number): Promise<PaymentRepositoryItem>;
    createPaymentRepository(data: Record<string, any>): Promise<PaymentRepositoryItem>;
    updatePaymentRepository(id: string | number, data: Record<string, any>): Promise<PaymentRepositoryItem>;
    deletePaymentRepository(id: string | number): Promise<any>;
    testOrder(id: string | number, data?: Record<string, any>): Promise<any>;
}
