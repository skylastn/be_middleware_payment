import { apiClient } from '@/shared/network/api_client';
import { LoginRequest } from '@/features/auth/domain/model/request/login_request';
import { LoginResponse } from '@/features/auth/domain/model/response/login_response';
import { User } from '@/features/auth/domain/model/response/user_response';

export class AuthRemoteDataSource {
    async login(request: LoginRequest): Promise<LoginResponse> {
        return apiClient<LoginResponse>('/api/login', {
            method: 'POST',
            body: JSON.stringify(request),
        });
    }

    async getMe(): Promise<User> {
        const res = await apiClient<any>('/api/admin/me');
        return res?.data ?? res;
    }

    async logout(): Promise<void> {
        await apiClient('/api/admin/logout', {
            method: 'POST',
        });
    }
}
