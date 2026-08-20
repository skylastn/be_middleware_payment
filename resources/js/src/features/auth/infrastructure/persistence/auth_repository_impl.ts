import { AuthRepository } from '../../domain/repository/auth_repository';
import { LoginRequest } from '../../domain/model/request/login_request';
import { LoginResponse } from '../../domain/model/response/login_response';
import { User } from '../../domain/model/response/user_response';
import { AuthLocalDataSource } from '../data_source/local/auth_local_data_source';
import { AuthRemoteDataSource } from '../data_source/remote/auth_remote_data_source';

export class AuthRepositoryImpl implements AuthRepository {
    constructor(
        private readonly local: AuthLocalDataSource = new AuthLocalDataSource(),
        private readonly remote: AuthRemoteDataSource = new AuthRemoteDataSource()
    ) {}

    get isLogin(): boolean {
        return this.local.hasToken();
    }

    get token(): string | null {
        return this.local.getToken();
    }

    async login(request: LoginRequest): Promise<LoginResponse> {
        const response = await this.remote.login(request);
        if (response?.token) {
            this.local.saveToken(response.token);
        }
        return response;
    }

    async getMe(): Promise<User> {
        return this.remote.getMe();
    }

    async logout(): Promise<void> {
        try {
            await this.remote.logout();
        } finally {
            this.local.clearToken();
        }
    }
}
