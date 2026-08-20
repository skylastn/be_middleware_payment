import { api } from '../shared/network/network';

export class ResourceService {
    static async list(url: string): Promise<any> {
        return api(url);
    }

    static async show(url: string): Promise<any> {
        return api(url);
    }

    static async create(url: string, data: Record<string, any>): Promise<any> {
        return api(url, {
            method: 'POST',
            body: JSON.stringify(data),
        });
    }

    static async update(url: string, data: Record<string, any>): Promise<any> {
        return api(url, {
            method: 'PUT',
            body: JSON.stringify(data),
        });
    }

    static async delete(url: string): Promise<any> {
        return api(url, {
            method: 'DELETE',
        });
    }

    static async resendCallback(url: string): Promise<any> {
        return api(url, {
            method: 'POST',
        });
    }
}
