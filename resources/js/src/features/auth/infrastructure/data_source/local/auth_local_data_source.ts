import { getStoredToken, removeStoredToken, setStoredToken } from '@/shared/utils/auth_utils';

export class AuthLocalDataSource {
    getToken(): string | null {
        return getStoredToken();
    }

    saveToken(token: string): void {
        setStoredToken(token);
    }

    clearToken(): void {
        removeStoredToken();
    }

    hasToken(): boolean {
        return Boolean(this.getToken());
    }
}
