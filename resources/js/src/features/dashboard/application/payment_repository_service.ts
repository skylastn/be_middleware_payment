import { PaymentRepositoryRepository } from '../domain/repository/payment_repository_repository';
import { PaymentRepositoryRepositoryImpl } from '../infrastructure/persistence/payment_repository_repository_impl';
import { PaymentRepositoryFilterRequest } from '../domain/model/request/payment/payment_repository_filter_request';
import { PaymentRepositoryItem, PaymentRepositoryListResponse } from '../domain/model/response/payment/payment_repository_response';

export class PaymentRepositoryService {
    private repo: PaymentRepositoryRepository;

    constructor(repo?: PaymentRepositoryRepository) {
        this.repo = repo ?? new PaymentRepositoryRepositoryImpl();
    }

    async getPaymentRepositories(params?: PaymentRepositoryFilterRequest): Promise<PaymentRepositoryListResponse> {
        return this.repo.getPaymentRepositories(params);
    }

    async getPaymentRepositoryById(id: string | number): Promise<PaymentRepositoryItem> {
        return this.repo.getPaymentRepositoryById(id);
    }

    async createPaymentRepository(data: Record<string, any>): Promise<PaymentRepositoryItem> {
        return this.repo.createPaymentRepository(data);
    }

    async updatePaymentRepository(id: string | number, data: Record<string, any>): Promise<PaymentRepositoryItem> {
        return this.repo.updatePaymentRepository(id, data);
    }

    async deletePaymentRepository(id: string | number): Promise<any> {
        return this.repo.deletePaymentRepository(id);
    }

    async testOrder(id: string | number, data: Record<string, any> = {}): Promise<any> {
        return this.repo.testOrder(id, data);
    }
}

export const paymentRepositoryService = new PaymentRepositoryService();
