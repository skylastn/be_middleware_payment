import { getStoredToken, removeStoredToken } from '../utils/auth_utils';

export async function apiClient<T = any>(url: string, options: RequestInit = {}): Promise<T> {
    const token = getStoredToken();
    const headers = new Headers(options.headers || {});
    headers.set('Accept', 'application/json');

    if (token) {
        headers.set('Authorization', `Bearer ${token}`);
    }

    if (options.body && !(options.body instanceof FormData) && !headers.has('Content-Type')) {
        headers.set('Content-Type', 'application/json');
    }

    const response = await fetch(url, { ...options, headers });
    const text = await response.text();
    let data: any = null;

    try {
        data = text ? JSON.parse(text) : null;
    } catch {
        data = text;
    }

    if (!response.ok) {
        if (response.status === 401) {
            removeStoredToken();
        }

        const message =
            (typeof data === 'object' && data !== null && (data.message || data.error)) ||
            `Request failed with status ${response.status}`;
        throw new Error(message);
    }

    return data as T;
}
