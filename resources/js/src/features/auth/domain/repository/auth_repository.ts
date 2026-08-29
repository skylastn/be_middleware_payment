import { LoginRequest } from '../model/request/login_request';
import { LoginResponse } from '../model/response/login_response';
import { User } from '../model/response/user_response';

export interface AuthRepository {
    get isLogin(): boolean;
    get token(): string | null;
    login(request: LoginRequest): Promise<LoginResponse>;
    getMe(): Promise<User>;
    logout(): Promise<void>;
}
