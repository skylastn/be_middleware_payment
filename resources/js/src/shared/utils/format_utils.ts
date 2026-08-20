export function title(value?: string | null): string {
    return String(value || '').replaceAll('_', ' ');
}

export function displayValue(value: unknown): string {
    if (Array.isArray(value) || (value && typeof value === 'object')) {
        return JSON.stringify(value, null, 2);
    }
    return String(value ?? '');
}

export function dataItems(payload: any): any[] {
    return Array.isArray(payload?.data) ? payload.data : [];
}

export function dataRecord(payload: any): Record<string, any> {
    return payload?.data || payload?.record || {};
}

export function navigate(path: string): void {
    window.history.pushState({}, '', path);
    window.dispatchEvent(new PopStateEvent('popstate'));
}
