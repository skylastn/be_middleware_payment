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

export function getTodayDateString(): string {
    const d = new Date();
    return d.toISOString().split('T')[0];
}

export function getMonthRange(date = new Date()): { startDate: string; endDate: string } {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const startDate = `${year}-${month}-01`;
    const lastDay = new Date(year, date.getMonth() + 1, 0).getDate();
    const endDate = `${year}-${month}-${String(lastDay).padStart(2, '0')}`;
    return { startDate, endDate };
}

export function getLastDaysRange(days: number): { startDate: string; endDate: string } {
    const end = new Date();
    const start = new Date();
    start.setDate(end.getDate() - (days - 1));
    return {
        startDate: start.toISOString().split('T')[0],
        endDate: end.toISOString().split('T')[0],
    };
}
