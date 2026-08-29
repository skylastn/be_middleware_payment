import { PaymentRepositoryRepository } from '../../domain/repository/payment_repository_repository';
import { PaymentRepositoryFilterRequest } from '../../domain/model/request/payment/payment_repository_filter_request';
import { PaymentRepositoryItem, PaymentRepositoryListResponse } from '../../domain/model/response/payment/payment_repository_response';
import { PaymentRepositoryRemoteDataSource } from '../data_source/remote/payment_repository_remote_data_source';

export class PaymentRepositoryRepositoryImpl implements PaymentRepositoryRepository {
    constructor(
        private readonly remote: PaymentRepositoryRemoteDataSource = new PaymentRepositoryRemoteDataSource()
    ) {}

    async getPaymentRepositories(params?: PaymentRepositoryFilterRequest): Promise<PaymentRepositoryListResponse> {
        return this.remote.getPaymentRepositories(params);
    }

    async getPaymentRepositoryById(id: string | number): Promise<PaymentRepositoryItem> {
        return this.remote.getPaymentRepositoryById(id);
    }

    async createPaymentRepository(data: Record<string, any>): Promise<PaymentRepositoryItem> {
        return this.remote.createPaymentRepository(data);
    }

    async updatePaymentRepository(id: string | number, data: Record<string, any>): Promise<PaymentRepositoryItem> {
        return this.remote.updatePaymentRepository(id, data);
    }

    async deletePaymentRepository(id: string | number): Promise<any> {
        return this.remote.deletePaymentRepository(id);
    }

    async testOrder(id: string | number, data: Record<string, any> = {}): Promise<any> {
        return this.remote.testOrder(id, data);
    }
}
