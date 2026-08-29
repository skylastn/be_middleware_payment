import { PaymentRepositoryFilterRequest, PaymentRepositoryItem, PaymentRepositoryListResponse } from '../model/payment/payment_repository_model';

export interface PaymentRepositoryRepository {
    getPaymentRepositories(params?: PaymentRepositoryFilterRequest): Promise<PaymentRepositoryListResponse>;
    getPaymentRepositoryById(id: string | number): Promise<PaymentRepositoryItem>;
    createPaymentRepository(data: Record<string, any>): Promise<PaymentRepositoryItem>;
    updatePaymentRepository(id: string | number, data: Record<string, any>): Promise<PaymentRepositoryItem>;
    deletePaymentRepository(id: string | number): Promise<any>;
    testOrder(id: string | number, data?: Record<string, any>): Promise<any>;
}
