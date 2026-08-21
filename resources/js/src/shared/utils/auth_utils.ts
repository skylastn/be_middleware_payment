export const TOKEN_KEY = 'backoffice_api_token';

export const getStoredToken = (): string => {
    return window.localStorage.getItem(TOKEN_KEY) || '';
};

export const setStoredToken = (token: string): void => {
    window.localStorage.setItem(TOKEN_KEY, token);
};

export const removeStoredToken = (): void => {
    window.localStorage.removeItem(TOKEN_KEY);
};
