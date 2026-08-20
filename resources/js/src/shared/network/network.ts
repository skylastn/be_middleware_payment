import { getStoredToken, removeStoredToken } from '../utils/auth_utils';

export async function api<T = any>(url: string, options: RequestInit = {}): Promise<T> {
    const token = getStoredToken();
    const response = await fetch(url, {
        ...options,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
            ...(options.headers || {}),
        },
    });

    if (!response.ok) {
        if (response.status === 401) {
            removeStoredToken();
        }

        const error = await response.json().catch(() => ({}));
        const message =
            error.message ||
            (error.errors ? Object.values(error.errors).flat()[0] : null) ||
            `Request failed with ${response.status}`;
        throw new Error(String(message));
    }

    return response.json();
}
