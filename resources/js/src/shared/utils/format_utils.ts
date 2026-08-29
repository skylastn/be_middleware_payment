export function title(value?: string | null): string {
    return String(value || '').replaceAll('_', ' ');
}

export function formatDate(value?: string | null): string {
    if (!value) return '-';
    const isoMatch = String(value).match(/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2})/);
    if (isoMatch) {
        return `${isoMatch[1]} ${isoMatch[2]}`;
    }
    return String(value);
}

export function displayValue(value: unknown): string {
    if (Array.isArray(value) || (value && typeof value === 'object')) {
        return JSON.stringify(value, null, 2);
    }
    if (typeof value === 'string') {
        const isoMatch = value.match(/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2})/);
        if (isoMatch) {
            return `${isoMatch[1]} ${isoMatch[2]}`;
        }
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
