import { User } from './user_response';

export interface LoginResponse {
    token: string;
    user: User;
}
