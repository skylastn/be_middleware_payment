import { api } from '../shared/network/network';
import { ApiResponse, User } from '../model/response_model';
import { setStoredToken, removeStoredToken } from '../shared/utils/auth_utils';

export class AuthService {
    static async login(email: string, password: string): Promise<User> {
        const data = await api<ApiResponse>('/api/login', {
            method: 'POST',
            body: JSON.stringify({ email, password }),
        });

        if (data.token) {
            setStoredToken(data.token);
        }

        return data.user || {};
    }

    static async getMe(): Promise<User | null> {
        const data = await api<ApiResponse>('/api/admin/me');
        return data.user || null;
    }

    static async logout(): Promise<void> {
        await api('/api/admin/logout', { method: 'POST' }).catch(() => null);
        removeStoredToken();
    }
}
