import { AuthRepository } from '../domain/repository/auth_repository';
import { AuthRepositoryImpl } from '../infrastructure/persistence/auth_repository_impl';
import { LoginRequest } from '../domain/model/request/login_request';
import { LoginResponse } from '../domain/model/response/login_response';
import { User } from '../domain/model/response/user_response';

export class AuthService {
    private repo: AuthRepository;

    constructor(repo?: AuthRepository) {
        this.repo = repo ?? new AuthRepositoryImpl();
    }

    get isLogin(): boolean {
        return this.repo.isLogin;
    }

    get token(): string | null {
        return this.repo.token;
    }

    async login(request: LoginRequest): Promise<LoginResponse> {
        return this.repo.login(request);
    }

    async getMe(): Promise<User> {
        return this.repo.getMe();
    }

    async logout(): Promise<void> {
        return this.repo.logout();
    }
}

export const authService = new AuthService();
