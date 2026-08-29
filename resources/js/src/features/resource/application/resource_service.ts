import { apiClient } from '@/shared/network/api_client';

export class ResourceService {
    static async list(endpoint: string): Promise<any> {
        return apiClient(endpoint);
    }

    static async show(endpoint: string): Promise<any> {
        return apiClient(endpoint);
    }

    static async create(endpoint: string, data: Record<string, any>): Promise<any> {
        return apiClient(endpoint, {
            method: 'POST',
            body: JSON.stringify(data),
        });
    }

    static async update(endpoint: string, data: Record<string, any>): Promise<any> {
        return apiClient(endpoint, {
            method: 'PUT',
            body: JSON.stringify(data),
        });
    }

    static async delete(endpoint: string): Promise<any> {
        return apiClient(endpoint, {
            method: 'DELETE',
        });
    }

    static async resendCallback(endpoint: string): Promise<any> {
        return apiClient(endpoint, {
            method: 'POST',
        });
    }

    static async setSuccess(endpoint: string): Promise<any> {
        return apiClient(endpoint, {
            method: 'POST',
        });
    }
}
