import './bootstrap';
import React, { useEffect, useMemo, useState } from 'react';
import { createRoot } from 'react-dom/client';

export type FieldType = 'text' | 'select' | 'textarea' | 'json';
export type Theme = 'light' | 'dark';

export interface FieldDefinition {
    type?: FieldType;
    label?: string;
    required?: boolean;
    options?: string[];
    default?: string;
}

export interface ResourceDefinition {
    label: string;
    singular: string;
    readonly?: boolean;
    columns: string[];
    fields: Record<string, FieldDefinition>;
    readonlyFields?: Record<string, FieldDefinition>;
    endpoints: {
        list: string;
        show: (id: string | number) => string;
        create?: string;
        update?: (id: string | number) => string;
        delete?: (id: string | number) => string;
        resend?: (id: string | number) => string;
    };
}

export type ResourceKey =
    | 'orders'
    | 'projects'
    | 'payment-gateways'
    | 'payment-repositories'
    | 'payment-methods'
    | 'payment-categories'
    | 'settings';

export interface User {
    id?: string | number;
    name?: string;
    email?: string;
    role?: string;
}

export interface DashboardData {
    updatedAt: string;
    summary: {
        orders: number;
        paidOrders: number;
        pendingOrders: number;
        failedOrders: number;
        projects: number;
        paymentGateways: number;
        paymentRepositories: number;
        paymentMethods: number;
    };
    statusMix: {
        success: number;
        pending: number;
        failedExpired: number;
        successDeg: number;
        pendingDeg: number;
    };
    modeCounts: Array<{
        mode: string;
        total: number;
    }>;
    recentOrders: Array<{
        reference: string;
        type: string;
        paymentMethod: string;
        status: string;
        statusClass: string;
        mode: string;
        createdAt?: string;
    }>;
    repositories: Array<{
        id: string;
        gateway: string;
        mode: string;
    }>;
}

export interface PaginationInfo {
    currentPage: number;
    lastPage: number;
    from: number;
    to: number;
    total: number;
    previousPageUrl: string | null;
    nextPageUrl: string | null;
}

export interface ApiPayload<T = Record<string, any>> {
    status?: boolean;
    code?: number;
    message?: string;
    data?: T;
    record?: T;
    perPage?: number;
    total?: number;
    currentPage?: number;
    token?: string;
    user?: User;
    errors?: Record<string, string[]>;
}

export type RouteInfo =
    | { page: 'dashboard'; resource?: undefined; id?: undefined }
    | { page: 'resource-index'; resource: ResourceKey; id?: undefined }
    | { page: 'resource-create'; resource: ResourceKey; id?: undefined }
    | { page: 'resource-edit'; resource: ResourceKey; id: string }
    | { page: 'resource-show'; resource: ResourceKey; id: string };

/* =========================================================================
   Theme Management Hook
   ========================================================================= */
function useTheme() {
    const [theme, setTheme] = useState<Theme>(() => {
        const saved = window.localStorage.getItem('backoffice_theme') as Theme;
        if (saved === 'dark' || saved === 'light') return saved;
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    });

    useEffect(() => {
        document.documentElement.setAttribute('data-theme', theme);
        window.localStorage.setItem('backoffice_theme', theme);
    }, [theme]);

    const toggleTheme = () => {
        setTheme((prev) => (prev === 'dark' ? 'light' : 'dark'));
    };

    return { theme, toggleTheme };
}

/* =========================================================================
   Modern SVG Icons
   ========================================================================= */
function IconSun(): React.JSX.Element {
    return (
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
            <circle cx="12" cy="12" r="5" />
            <line x1="12" y1="1" x2="12" y2="3" />
            <line x1="12" y1="21" x2="12" y2="23" />
            <line x1="4.22" y1="4.22" x2="5.64" y2="5.64" />
            <line x1="18.36" y1="18.36" x2="19.78" y2="19.78" />
            <line x1="1" y1="12" x2="3" y2="12" />
            <line x1="21" y1="12" x2="23" y2="12" />
            <line x1="4.22" y1="19.78" x2="5.64" y2="18.36" />
            <line x1="18.36" y1="5.64" x2="19.78" y2="4.22" />
        </svg>
    );
}

function IconMoon(): React.JSX.Element {
    return (
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
        </svg>
    );
}

function IconDashboard(): React.JSX.Element {
    return (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <rect x="3" y="3" width="7" height="9" rx="1.5" />
            <rect x="14" y="3" width="7" height="5" rx="1.5" />
            <rect x="14" y="12" width="7" height="9" rx="1.5" />
            <rect x="3" y="16" width="7" height="5" rx="1.5" />
        </svg>
    );
}

function IconOrders(): React.JSX.Element {
    return (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2 2V6l-3-4z" />
            <line x1="3" y1="6" x2="21" y2="6" />
            <path d="M16 10a4 4 0 0 1-8 0" />
        </svg>
    );
}

function IconProjects(): React.JSX.Element {
    return (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z" />
        </svg>
    );
}

function IconGateways(): React.JSX.Element {
    return (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <rect x="2" y="2" width="20" height="8" rx="2" ry="2" />
            <rect x="2" y="14" width="20" height="8" rx="2" ry="2" />
            <line x1="6" y1="6" x2="6.01" y2="6" />
            <line x1="6" y1="18" x2="6.01" y2="18" />
        </svg>
    );
}

function IconRepositories(): React.JSX.Element {
    return (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4" />
        </svg>
    );
}

function IconMethods(): React.JSX.Element {
    return (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <rect x="1" y="4" width="22" height="16" rx="2" ry="2" />
            <line x1="1" y1="10" x2="23" y2="10" />
        </svg>
    );
}

function IconCategories(): React.JSX.Element {
    return (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z" />
            <line x1="7" y1="7" x2="7.01" y2="7" />
        </svg>
    );
}

function IconSettings(): React.JSX.Element {
    return (
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <circle cx="12" cy="12" r="3" />
            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z" />
        </svg>
    );
}

function getResourceIcon(key: string): React.JSX.Element {
    switch (key) {
        case 'dashboard': return <IconDashboard />;
        case 'orders': return <IconOrders />;
        case 'projects': return <IconProjects />;
        case 'payment-gateways': return <IconGateways />;
        case 'payment-repositories': return <IconRepositories />;
        case 'payment-methods': return <IconMethods />;
        case 'payment-categories': return <IconCategories />;
        case 'settings': return <IconSettings />;
        default: return <IconDashboard />;
    }
}

function IconSearch(): React.JSX.Element {
    return (
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
            <circle cx="11" cy="11" r="8" />
            <line x1="21" y1="21" x2="16.65" y2="16.65" />
        </svg>
    );
}

function IconX(): React.JSX.Element {
    return (
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
        </svg>
    );
}

function IconCopy(): React.JSX.Element {
    return (
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <rect x="9" y="9" width="13" height="13" rx="2" ry="2" />
            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
        </svg>
    );
}

function IconCheck(): React.JSX.Element {
    return (
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#10b981" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
            <polyline points="20 6 9 17 4 12" />
        </svg>
    );
}

function IconPlus(): React.JSX.Element {
    return (
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
            <line x1="12" y1="5" x2="12" y2="19" />
            <line x1="5" y1="12" x2="19" y2="12" />
        </svg>
    );
}

function IconArrowLeft(): React.JSX.Element {
    return (
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
            <line x1="19" y1="12" x2="5" y2="12" />
            <polyline points="12 19 5 12 12 5" />
        </svg>
    );
}

function CopyButton({ text }: { text: string }): React.JSX.Element {
    const [copied, setCopied] = useState<boolean>(false);

    const copy = (e: React.MouseEvent) => {
        e.stopPropagation();
        if (!text) return;
        navigator.clipboard.writeText(text);
        setCopied(true);
        setTimeout(() => setCopied(false), 1600);
    };

    return (
        <button type="button" className="copy-btn" onClick={copy} title={copied ? 'Copied!' : 'Copy to clipboard'}>
            {copied ? <IconCheck /> : <IconCopy />}
        </button>
    );
}

/* =========================================================================
   Resource Definitions
   ========================================================================= */
const resourceDefinitions: Record<ResourceKey, ResourceDefinition> = {
    orders: {
        label: 'Orders',
        singular: 'Order',
        readonly: true,
        columns: ['reference', 'type', 'payment_method', 'status', 'mode', 'created_at'],
        fields: {},
        endpoints: {
            list: '/api/order',
            show: (id: string | number) => `/api/order/${id}`,
            resend: (id: string | number) => `/api/order/${id}/resend-callback`,
        },
    },
    projects: {
        label: 'Projects',
        singular: 'Project',
        columns: ['name', 'type', 'slug', 'callback', 'created_at'],
        fields: {
            name: { type: 'text', required: true },
            type: { type: 'text', required: true },
            slug: { type: 'select', required: true, options: ['midtrans', 'xendit', 'duitku', 'spnpay', 'stripe'] },
            callback: { type: 'textarea', required: true },
        },
        readonlyFields: {
            key: { type: 'text' },
            secure: { type: 'text' },
            value: { type: 'textarea' },
        },
        endpoints: {
            list: '/api/project',
            show: (id: string | number) => `/api/project/${id}`,
            create: '/api/project/create',
            update: (id: string | number) => `/api/project/${id}`,
            delete: (id: string | number) => `/api/project/${id}`,
        },
    },
    'payment-gateways': {
        label: 'Payment Gateways',
        singular: 'Payment Gateway',
        columns: ['key', 'name', 'description', 'created_at'],
        fields: {
            key: { type: 'text', required: true },
            name: { type: 'text', required: true },
            description: { type: 'textarea', required: true },
        },
        endpoints: {
            list: '/api/payment/getPaymentGateway',
            show: (id: string | number) => `/api/payment/gateway/${id}`,
            create: '/api/payment/gateway/create',
            update: (id: string | number) => `/api/payment/gateway/${id}`,
            delete: (id: string | number) => `/api/payment/gateway/${id}`,
        },
    },
    'payment-repositories': {
        label: 'Payment Repositories',
        singular: 'Payment Repository',
        columns: ['key', 'payment_gateway_id', 'mode', 'value', 'created_at'],
        fields: {
            payment_gateway_id: { label: 'Payment Gateway ID', type: 'text', required: true },
            key: { type: 'text' },
            mode: { type: 'select', required: true, options: ['sandbox', 'prod'] },
            value: { type: 'json', required: true },
        },
        endpoints: {
            list: '/api/payment/getPaymentRepository',
            show: (id: string | number) => `/api/payment/repository/${id}`,
            create: '/api/payment/repository/create',
            update: (id: string | number) => `/api/payment/repository/${id}`,
            delete: (id: string | number) => `/api/payment/repository/${id}`,
        },
    },
    'payment-methods': {
        label: 'Payment Methods',
        singular: 'Payment Method',
        columns: ['key', 'name', 'type', 'from', 'bankCode', 'value'],
        fields: {
            key: { type: 'text', required: true },
            name: { type: 'text', required: true },
            type: { type: 'text', required: true },
            from: { type: 'text', required: true },
            bankCode: { label: 'Bank Code', type: 'text' },
            value: { type: 'text' },
        },
        endpoints: {
            list: '/api/payment/getPaymentMethod',
            show: (id: string | number) => `/api/payment/method/${id}`,
            create: '/api/payment/method/create',
            update: (id: string | number) => `/api/payment/method/${id}`,
            delete: (id: string | number) => `/api/payment/method/${id}`,
        },
    },
    'payment-categories': {
        label: 'Payment Categories',
        singular: 'Payment Category',
        columns: ['key', 'title', 'detail', 'created_at'],
        fields: {
            key: { type: 'text', required: true },
            title: { type: 'text', required: true },
            detail: { type: 'textarea', required: true },
        },
        endpoints: {
            list: '/api/payment/getPaymentCategory',
            show: (id: string | number) => `/api/payment/category/${id}`,
            create: '/api/payment/category/create',
            update: (id: string | number) => `/api/payment/category/${id}`,
            delete: (id: string | number) => `/api/payment/category/${id}`,
        },
    },
    settings: {
        label: 'Settings',
        singular: 'Setting',
        columns: ['key', 'value', 'updated_at'],
        fields: {
            key: { type: 'text', required: true },
            value: { type: 'textarea', required: true },
        },
        endpoints: {
            list: '/api/payment/getSetting',
            show: (id: string | number) => `/api/payment/setting/${id}`,
            create: '/api/payment/setting/create',
            update: (id: string | number) => `/api/payment/setting/${id}`,
            delete: (id: string | number) => `/api/payment/setting/${id}`,
        },
    },
};

const resources: Array<{ key: ResourceKey; label: string }> = (
    Object.entries(resourceDefinitions) as [ResourceKey, ResourceDefinition][]
).map(([key, definition]) => ({
    key,
    label: definition.label,
}));

const tokenKey = 'backoffice_api_token';

function storedToken(): string {
    return window.localStorage.getItem(tokenKey) || '';
}

async function api<T = any>(url: string, options: RequestInit = {}): Promise<T> {
    const token = storedToken();
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
            window.localStorage.removeItem(tokenKey);
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

async function login(email: string, password: string): Promise<User> {
    const data = await api<ApiPayload>('/api/login', {
        method: 'POST',
        body: JSON.stringify({ email, password }),
    });

    if (data.token) {
        window.localStorage.setItem(tokenKey, data.token);
    }

    return data.user || {};
}

function navigate(path: string): void {
    window.history.pushState({}, '', path);
    window.dispatchEvent(new PopStateEvent('popstate'));
}

function title(value?: string | null): string {
    return String(value || '').replaceAll('_', ' ');
}

function dataItems(payload: any): any[] {
    return Array.isArray(payload?.data) ? payload.data : [];
}

function dataRecord(payload: any): Record<string, any> {
    return payload?.data || payload?.record || {};
}

function pagination(payload: any): PaginationInfo {
    const perPage = Number(payload?.perPage || 15);
    const total = Number(payload?.total || dataItems(payload).length);
    const currentPage = Number(payload?.currentPage || 1);

    return {
        currentPage,
        lastPage: Math.max(1, Math.ceil(total / Math.max(perPage, 1))),
        from: total ? (currentPage - 1) * perPage + 1 : 0,
        to: Math.min(currentPage * perPage, total),
        total,
        previousPageUrl: currentPage > 1 ? `${window.location.pathname}?page=${currentPage - 1}` : null,
        nextPageUrl: currentPage * perPage < total ? `${window.location.pathname}?page=${currentPage + 1}` : null,
    };
}

function displayValue(value: unknown): string {
    if (Array.isArray(value) || (value && typeof value === 'object')) {
        return JSON.stringify(value, null, 2);
    }

    return String(value ?? '');
}

function renderBadge(column: string, val: any): React.JSX.Element {
    const str = String(val || '').toUpperCase();
    if (column === 'status') {
        if (str === 'SUCCESS' || str === 'PAID' || str === '00') {
            return <span className="badge success">{str}</span>;
        }
        if (str === 'PENDING' || str === 'OPEN' || str === 'WAITING') {
            return <span className="badge warning">{str}</span>;
        }
        if (str === 'FAILED' || str === 'EXPIRED' || str === 'CANCEL') {
            return <span className="badge danger">{str}</span>;
        }
    }
    if (column === 'mode') {
        if (str === 'PROD' || str === 'PRODUCTION') {
            return <span className="badge success">PROD</span>;
        }
        return <span className="badge blue">SANDBOX</span>;
    }
    return <span>{String(val ?? '-')}</span>;
}

/* =========================================================================
   Dashboard Page Component
   ========================================================================= */
interface StatCardProps {
    label: string;
    value?: number | string | null;
    note: string;
    tone?: string;
}

function StatCard({ label, value, note, tone = '' }: StatCardProps): React.JSX.Element {
    return (
        <div className={`panel stat ${tone}`}>
            <div className="stat-label">{label}</div>
            <div className="stat-value">{Number(value || 0).toLocaleString()}</div>
            <div className="stat-note">{note}</div>
        </div>
    );
}

function DashboardPage(): React.JSX.Element {
    const [data, setData] = useState<DashboardData | null>(null);
    const [error, setError] = useState<string>('');

    useEffect(() => {
        api<DashboardData>('/api/admin/dashboard')
            .then(setData)
            .catch((exception: Error) => setError(exception.message));
    }, []);

    if (error) {
        return <div className="panel empty">{error}</div>;
    }

    if (!data) {
        return <div className="panel empty">Loading dashboard data...</div>;
    }

    const maxMode = Math.max(...data.modeCounts.map((mode) => Number(mode.total || 0)), 1);

    return (
        <>
            <PageTitle
                eyebrow="Live Overview"
                title="Payment Monitoring Dashboard"
                subtitle="Real-time operational metrics for orders, projects, and gateway transactions."
            >
                <span className="filter-badge">Updated {data.updatedAt}</span>
            </PageTitle>

            <section className="grid stats">
                <StatCard label="Total Orders" value={data.summary.orders} note="All captured transactions" tone="blue" />
                <StatCard label="Success" value={data.summary.paidOrders} note="Completed payments" />
                <StatCard label="Pending" value={data.summary.pendingOrders} note="Awaiting callback" tone="warning" />
                <StatCard label="Failed / Expired" value={data.summary.failedOrders} note="Failed / canceled" tone="danger" />
            </section>

            <section className="grid stats">
                <StatCard label="Projects" value={data.summary.projects} note="Registered merchant apps" />
                <StatCard label="Gateways" value={data.summary.paymentGateways} note="Supported gateways" tone="purple" />
                <StatCard label="Repositories" value={data.summary.paymentRepositories} note="Credential sets" tone="blue" />
                <StatCard label="Methods" value={data.summary.paymentMethods} note="Payment channels" />
            </section>

            <section className="chart-grid">
                <div className="panel">
                    <PanelHeader title="Status Distribution" kicker="Success, pending, and failed ratio" />
                    <div
                        className="donut"
                        style={
                            {
                                '--success-deg': `${data.statusMix.successDeg}deg`,
                                '--pending-deg': `${data.statusMix.pendingDeg}deg`,
                            } as React.CSSProperties
                        }
                    />
                    <div className="list">
                        <ListRow label="Success" value={data.statusMix.success} />
                        <ListRow label="Pending" value={data.statusMix.pending} />
                        <ListRow label="Failed / Expired" value={data.statusMix.failedExpired} />
                    </div>
                </div>
                <div className="panel">
                    <PanelHeader title="Environment Traffic" kicker="Transaction volume by mode" />
                    <div className="bar-list">
                        {data.modeCounts.map((mode) => (
                            <div className="bar-row" key={mode.mode}>
                                <span className="list-title">{mode.mode.toUpperCase()}</span>
                                <span className="bar-track">
                                    <span
                                        className={`bar-fill ${mode.mode === 'prod' ? 'blue' : 'warning'}`}
                                        style={{
                                            width: `${Math.max(
                                                4,
                                                Math.round((Number(mode.total || 0) / maxMode) * 100)
                                            )}%`,
                                        }}
                                    />
                                </span>
                                <strong>{Number(mode.total || 0).toLocaleString()}</strong>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            <section className="grid columns">
                <div className="panel">
                    <PanelHeader
                        title="Recent Orders"
                        kicker="Latest payment requests"
                        aside={`${data.recentOrders.length} latest`}
                    />
                    <DataTable columns={['Reference', 'Project', 'Method', 'Status', 'Mode', 'Created']}>
                        {data.recentOrders.map((order) => (
                            <tr key={order.reference}>
                                <td className="mono">
                                    {order.reference}
                                    <CopyButton text={order.reference} />
                                </td>
                                <td>{order.type}</td>
                                <td>{order.paymentMethod || '-'}</td>
                                <td>
                                    <span className={`badge ${order.statusClass}`}>{order.status}</span>
                                </td>
                                <td>
                                    <span className={`badge ${order.mode === 'prod' ? 'success' : 'blue'}`}>
                                        {order.mode}
                                    </span>
                                </td>
                                <td>{order.createdAt || '-'}</td>
                            </tr>
                        ))}
                    </DataTable>
                </div>
                <div className="panel">
                    <PanelHeader title="Active Repositories" kicker="Gateway credentials by mode" />
                    <div className="list">
                        {data.repositories.map((repository) => (
                            <div className="list-row" key={repository.id}>
                                <div className="list-main">
                                    <span className="list-title">{repository.gateway}</span>
                                    <div className="muted mono">
                                        {repository.id}
                                        <CopyButton text={repository.id} />
                                    </div>
                                </div>
                                <span className={`badge ${repository.mode === 'prod' ? 'success' : 'blue'}`}>
                                    {repository.mode}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
            </section>
        </>
    );
}

/* =========================================================================
   Resource Index Page (Search & Filters)
   ========================================================================= */
interface ResourceIndexProps {
    resource: ResourceKey;
}

function ResourceIndex({ resource }: ResourceIndexProps): React.JSX.Element {
    const definition = resourceDefinitions[resource];
    const [payload, setPayload] = useState<any>(null);
    const [error, setError] = useState<string>('');
    const [searchTerm, setSearchTerm] = useState<string>('');
    const [selectedMode, setSelectedMode] = useState<string>('all');
    const [selectedStatus, setSelectedStatus] = useState<string>('all');

    const load = (url = definition.endpoints.list) => {
        setError('');
        api(url)
            .then(setPayload)
            .catch((exception: Error) => setError(exception.message));
    };

    useEffect(() => {
        setPayload(null);
        setSearchTerm('');
        setSelectedMode('all');
        setSelectedStatus('all');
        load();
    }, [resource]);

    if (error) {
        return <div className="panel empty">{error}</div>;
    }

    if (!payload) {
        return <div className="panel empty">Loading records...</div>;
    }

    const records = dataItems(payload);
    const page = pagination(payload);

    // Instant Filter & Search across all columns
    const filteredRecords = records.filter((record: Record<string, any>) => {
        if (searchTerm.trim()) {
            const term = searchTerm.toLowerCase().trim();
            const matches = Object.values(record).some((val) => {
                if (val === null || val === undefined) return false;
                if (typeof val === 'object') {
                    return JSON.stringify(val).toLowerCase().includes(term);
                }
                return String(val).toLowerCase().includes(term);
            });
            if (!matches) return false;
        }

        if (selectedMode !== 'all' && record.mode) {
            if (String(record.mode).toLowerCase() !== selectedMode.toLowerCase()) {
                return false;
            }
        }

        if (selectedStatus !== 'all' && record.status) {
            if (String(record.status).toLowerCase() !== selectedStatus.toLowerCase()) {
                return false;
            }
        }

        return true;
    });

    const isFiltered = searchTerm.trim() !== '' || selectedMode !== 'all' || selectedStatus !== 'all';

    const clearFilters = () => {
        setSearchTerm('');
        setSelectedMode('all');
        setSelectedStatus('all');
    };

    async function destroy(record: any) {
        if (!definition.endpoints.delete) return;
        if (!window.confirm(`Are you sure you want to delete ${definition.singular} #${record.id || record.key}?`)) {
            return;
        }

        await api(definition.endpoints.delete(record.id), { method: 'DELETE' });
        load();
    }

    async function resend(record: any) {
        if (!definition.endpoints.resend) return;
        if (!window.confirm(`Resend merchant callback for order ${record.reference}?`)) {
            return;
        }

        await api(definition.endpoints.resend(record.id), { method: 'POST' });
        load();
    }

    return (
        <>
            <PageTitle
                eyebrow="Backoffice"
                title={definition.label}
                subtitle={
                    definition.readonly
                        ? `View and monitor ${definition.label.toLowerCase()} records.`
                        : `Manage, search, and configure ${definition.label.toLowerCase()}.`
                }
            >
                {!definition.readonly && definition.endpoints.create && (
                    <button className="button primary" onClick={() => navigate(`/admin/${resource}/create`)}>
                        <IconPlus /> Create {definition.singular}
                    </button>
                )}
            </PageTitle>

            <div className="panel">
                {/* Search & Filter Toolbar */}
                <div className="filter-bar">
                    <div className="search-box">
                        <span className="search-icon"><IconSearch /></span>
                        <input
                            className="search-input"
                            type="text"
                            placeholder={`Search in ${definition.label.toLowerCase()}...`}
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                        />
                        {searchTerm && (
                            <button className="search-clear" type="button" onClick={() => setSearchTerm('')}>
                                <IconX />
                            </button>
                        )}
                    </div>

                    <div className="filter-group">
                        {definition.columns.includes('mode') && (
                            <select
                                className="filter-select"
                                value={selectedMode}
                                onChange={(e) => setSelectedMode(e.target.value)}
                            >
                                <option value="all">All Modes</option>
                                <option value="sandbox">Sandbox</option>
                                <option value="prod">Production</option>
                            </select>
                        )}

                        {definition.columns.includes('status') && (
                            <select
                                className="filter-select"
                                value={selectedStatus}
                                onChange={(e) => setSelectedStatus(e.target.value)}
                            >
                                <option value="all">All Statuses</option>
                                <option value="SUCCESS">SUCCESS</option>
                                <option value="PENDING">PENDING</option>
                                <option value="FAILED">FAILED</option>
                                <option value="EXPIRED">EXPIRED</option>
                            </select>
                        )}

                        {isFiltered && (
                            <button className="button" type="button" onClick={clearFilters}>
                                <IconX /> Clear Filter
                            </button>
                        )}

                        <span className="filter-badge">
                            {filteredRecords.length} {filteredRecords.length === 1 ? 'record' : 'records'}
                        </span>
                    </div>
                </div>

                <DataTable columns={[...definition.columns.map(title), 'Actions']}>
                    {filteredRecords.length ? (
                        filteredRecords.map((record: any) => (
                            <tr key={record.id || record.key || record.reference}>
                                {definition.columns.map((column) => {
                                    const rawVal = record[column];
                                    const isBadgeCol = ['status', 'mode'].includes(column);

                                    if (isBadgeCol) {
                                        return <td key={column}>{renderBadge(column, rawVal)}</td>;
                                    }

                                    const fullValue = displayValue(rawVal || '-');
                                    const max = resource === 'payment-repositories' && column === 'value' ? 180 : null;
                                    const value = max && fullValue.length > max ? `${fullValue.slice(0, max)}...` : fullValue;
                                    const isMono = ['id', 'key', 'reference', 'value', 'callback', 'token'].includes(column);

                                    return (
                                        <td className={isMono ? 'mono' : ''} title={fullValue} key={column}>
                                            {value}
                                            {isMono && rawVal && <CopyButton text={fullValue} />}
                                        </td>
                                    );
                                })}
                                <td>
                                    <div className="actions">
                                        {resource === 'orders' && (
                                            <button
                                                className="button"
                                                onClick={() => navigate(`/admin/${resource}/${record.id}`)}
                                            >
                                                View
                                            </button>
                                        )}
                                        {resource === 'orders' && record.status === 'SUCCESS' && (
                                            <button className="button" onClick={() => resend(record)}>
                                                Resend Callback
                                            </button>
                                        )}
                                        {!definition.readonly && definition.endpoints.update && (
                                            <button
                                                className="button"
                                                onClick={() => navigate(`/admin/${resource}/${record.id}/edit`)}
                                            >
                                                Edit
                                            </button>
                                        )}
                                        {!definition.readonly && definition.endpoints.delete && (
                                            <button className="button danger" onClick={() => destroy(record)}>
                                                Delete
                                            </button>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))
                    ) : (
                        <tr>
                            <td className="empty" colSpan={definition.columns.length + 1}>
                                {isFiltered ? 'No matching records found for this filter.' : 'No records found.'}
                            </td>
                        </tr>
                    )}
                </DataTable>
            </div>

            <div className="pagination">
                <div>
                    Showing {filteredRecords.length} {isFiltered ? `(filtered from ${page.total})` : `of ${page.total}`} results
                </div>
                <div className="pagination-actions">
                    <button
                        className="button"
                        disabled={page.currentPage <= 1}
                        onClick={() => load(`${definition.endpoints.list}?page=${page.currentPage - 1}`)}
                    >
                        Previous
                    </button>
                    <span className="button">
                        Page {page.currentPage} of {page.lastPage}
                    </span>
                    <button
                        className="button"
                        disabled={page.currentPage >= page.lastPage}
                        onClick={() => load(`${definition.endpoints.list}?page=${page.currentPage + 1}`)}
                    >
                        Next
                    </button>
                </div>
            </div>
        </>
    );
}

/* =========================================================================
   Resource Form Component (Create / Edit + Strict JSON Validation)
   ========================================================================= */
interface ResourceFormProps {
    resource: ResourceKey;
    id?: string;
}

function ResourceForm({ resource, id }: ResourceFormProps): React.JSX.Element {
    const definition = resourceDefinitions[resource];
    const isEdit = Boolean(id);
    const [values, setValues] = useState<Record<string, any>>({});
    const [readonlyValues, setReadonlyValues] = useState<Record<string, any>>({});
    const [loadError, setLoadError] = useState<string>('');
    const [formError, setFormError] = useState<string>('');
    const [submitting, setSubmitting] = useState<boolean>(false);

    useEffect(() => {
        setLoadError('');
        setFormError('');
        setValues({});
        setReadonlyValues({});
        if (!isEdit || !id) {
            return;
        }

        api(definition.endpoints.show(id))
            .then((payload) => {
                const record = dataRecord(payload);
                const formatted = { ...(record || {}) };
                for (const [name, field] of Object.entries(definition.fields || {})) {
                    if (field.type === 'json' && formatted[name] && typeof formatted[name] === 'object') {
                        formatted[name] = JSON.stringify(formatted[name], null, 2);
                    }
                }
                setValues(formatted);
                setReadonlyValues(record || {});
            })
            .catch((exception: Error) => setLoadError(exception.message));
    }, [resource, id, isEdit]);

    if (loadError) {
        return <div className="panel empty">{loadError}</div>;
    }

    async function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setFormError('');

        const payloadValues = { ...values };

        // 1. Strict Client-side JSON & Field Validation
        for (const [name, field] of Object.entries(definition.fields || {})) {
            if (field.type === 'json') {
                const raw = payloadValues[name];
                const fieldLabel = field.label || title(name);

                if (raw === undefined || raw === null || (typeof raw === 'string' && !raw.trim())) {
                    if (field.required) {
                        setFormError(`Field "${fieldLabel}" is required.`);
                        return;
                    }
                    payloadValues[name] = {};
                    continue;
                }

                if (typeof raw === 'string') {
                    let parsed: any;
                    try {
                        parsed = JSON.parse(raw);
                    } catch (parseError: any) {
                        setFormError(`Invalid JSON syntax in "${fieldLabel}": ${parseError?.message || 'Syntax error'}`);
                        return;
                    }

                    if (typeof parsed !== 'object' || parsed === null || Array.isArray(parsed)) {
                        setFormError(`Field "${fieldLabel}" must be a valid JSON object (e.g. {"key": "value"}).`);
                        return;
                    }

                    payloadValues[name] = parsed;
                }
            }
        }

        const targetUrl = isEdit && id && definition.endpoints.update
            ? definition.endpoints.update(id)
            : definition.endpoints.create;

        if (!targetUrl) return;

        setSubmitting(true);
        try {
            await api(targetUrl, {
                method: isEdit ? 'PUT' : 'POST',
                body: JSON.stringify(payloadValues),
            });
            navigate(`/admin/${resource}`);
        } catch (exception: any) {
            setFormError(exception.message || 'Failed to save record.');
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <>
            <PageTitle
                eyebrow="Configuration"
                title={`${isEdit ? 'Edit' : 'Create'} ${definition.singular}`}
                subtitle={`Configure ${definition.singular.toLowerCase()} parameters and details.`}
            >
                <button className="button" onClick={() => navigate(`/admin/${resource}`)}>
                    <IconArrowLeft /> Back to List
                </button>
            </PageTitle>

            <form className="panel form-grid" onSubmit={submit}>
                {formError && (
                    <div className="field full">
                        <div className="alert">{formError}</div>
                    </div>
                )}

                {Object.entries(definition.fields).map(([name, field]) => (
                    <Field
                        key={name}
                        name={name}
                        field={field}
                        value={values[name] ?? field.default ?? ''}
                        onChange={(value) => setValues((current) => ({ ...current, [name]: value }))}
                    />
                ))}

                {isEdit &&
                    Object.entries(definition.readonlyFields || {}).map(([name, field]) => (
                        <Field key={name} name={name} field={field} value={readonlyValues[name] ?? ''} readonly />
                    ))}

                <div className="field full">
                    <button className="button primary" type="submit" disabled={submitting}>
                        {submitting ? 'Saving...' : isEdit ? 'Save Changes' : 'Create Record'}
                    </button>
                </div>
            </form>
        </>
    );
}

/* =========================================================================
   Resource Show Component (Read-Only Detail)
   ========================================================================= */
interface ResourceShowProps {
    resource: ResourceKey;
    id: string;
}

function ResourceShow({ resource, id }: ResourceShowProps): React.JSX.Element {
    const definition = resourceDefinitions[resource];
    const [payload, setPayload] = useState<any>(null);
    const [error, setError] = useState<string>('');

    useEffect(() => {
        api(definition.endpoints.show(id))
            .then(setPayload)
            .catch((exception: Error) => setError(exception.message));
    }, [resource, id]);

    if (error) {
        return <div className="panel empty">{error}</div>;
    }

    if (!payload) {
        return <div className="panel empty">Loading record details...</div>;
    }

    const record = dataRecord(payload);

    return (
        <>
            <PageTitle
                eyebrow="Audit Detail"
                title={`View ${definition.singular} Details`}
                subtitle="Complete operational metadata and transaction payload."
            >
                <button className="button" onClick={() => navigate(`/admin/${resource}`)}>
                    <IconArrowLeft /> Back to List
                </button>
            </PageTitle>
            <div className="panel detail-list">
                {Object.entries(record).map(([name, value]) => (
                    <div className={`detail-row ${displayValue(value).length > 80 ? 'wide' : ''}`} key={name}>
                        <span className="label">{title(name)}</span>
                        <div className="mono">
                            {displayValue(value) || '-'}
                            {value && <CopyButton text={displayValue(value)} />}
                        </div>
                    </div>
                ))}
            </div>
        </>
    );
}

/* =========================================================================
   Form Field Component (with JSON Prettifier)
   ========================================================================= */
interface FieldProps {
    name: string;
    field: FieldDefinition;
    value: any;
    onChange?: (value: string) => void;
    readonly?: boolean;
}

function Field({ name, field, value, onChange = () => {}, readonly = false }: FieldProps): React.JSX.Element {
    const type = field.type || 'text';
    const label = field.label || title(name);
    const className = `field ${['textarea', 'json'].includes(type) ? 'full' : ''}`;
    const stringValue = typeof value === 'object' && value !== null ? JSON.stringify(value, null, 2) : String(value ?? '');

    const formatJson = () => {
        try {
            const parsed = JSON.parse(stringValue);
            onChange(JSON.stringify(parsed, null, 2));
        } catch {
            // keep raw if syntax error
        }
    };

    return (
        <label className={className}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <span className="label">{label}</span>
                {type === 'json' && !readonly && (
                    <button type="button" className="button" style={{ minHeight: '26px', padding: '2px 8px', fontSize: '11px' }} onClick={formatJson}>
                        Format JSON
                    </button>
                )}
            </div>
            {type === 'select' ? (
                <select
                    className="input"
                    value={value || ''}
                    disabled={readonly}
                    required={field.required}
                    onChange={(event) => onChange(event.target.value)}
                >
                    {(field.options || []).map((option) => (
                        <option value={option} key={option}>
                            {option}
                        </option>
                    ))}
                </select>
            ) : ['textarea', 'json'].includes(type) ? (
                <textarea
                    className="input mono"
                    rows={type === 'json' ? 9 : 4}
                    value={stringValue}
                    readOnly={readonly}
                    required={field.required}
                    placeholder={type === 'json' ? '{\n  "key": "value"\n}' : ''}
                    onChange={(event) => onChange(event.target.value)}
                />
            ) : (
                <input
                    className={readonly ? 'input mono' : 'input'}
                    value={stringValue}
                    readOnly={readonly}
                    required={field.required}
                    onChange={(event) => onChange(event.target.value)}
                />
            )}
        </label>
    );
}

/* =========================================================================
   Common UI Components
   ========================================================================= */
interface PageTitleProps {
    eyebrow: string;
    title: string;
    subtitle: string;
    children?: React.ReactNode;
}

function PageTitle({ eyebrow, title, subtitle, children }: PageTitleProps): React.JSX.Element {
    return (
        <div className="page-title">
            <div>
                <div className="eyebrow">{eyebrow}</div>
                <h1>{title}</h1>
                <div className="subtitle">{subtitle}</div>
            </div>
            {children && <div className="toolbar">{children}</div>}
        </div>
    );
}

interface PanelHeaderProps {
    title: string;
    kicker: string;
    aside?: React.ReactNode;
}

function PanelHeader({ title, kicker, aside }: PanelHeaderProps): React.JSX.Element {
    return (
        <div className="panel-header">
            <div>
                <h2 className="panel-title">{title}</h2>
                <div className="panel-kicker">{kicker}</div>
            </div>
            {aside && (typeof aside === 'string' ? <span className="filter-badge">{aside}</span> : aside)}
        </div>
    );
}

interface ListRowProps {
    label: string;
    value: number | string;
}

function ListRow({ label, value }: ListRowProps): React.JSX.Element {
    return (
        <div className="list-row">
            <span className="list-title">{label}</span>
            <strong>{Number(value || 0).toLocaleString()}</strong>
        </div>
    );
}

interface DataTableProps {
    columns: string[];
    children: React.ReactNode;
}

function DataTable({ columns, children }: DataTableProps): React.JSX.Element {
    return (
        <div className="table-wrap">
            <table>
                <thead>
                    <tr>
                        {columns.map((column) => (
                            <th key={column}>{column}</th>
                        ))}
                    </tr>
                </thead>
                <tbody>{children}</tbody>
            </table>
        </div>
    );
}

/* =========================================================================
   Auth & Login Page
   ========================================================================= */
interface LoginPageProps {
    onLogin: (user: User) => void;
    theme: Theme;
    onToggleTheme: () => void;
}

function LoginPage({ onLogin, theme, onToggleTheme }: LoginPageProps): React.JSX.Element {
    const [email, setEmail] = useState<string>('');
    const [password, setPassword] = useState<string>('');
    const [error, setError] = useState<string>('');
    const [loading, setLoading] = useState<boolean>(false);

    async function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setError('');
        setLoading(true);

        try {
            onLogin(await login(email, password));
            navigate('/dashboard');
        } catch (exception: any) {
            setError(exception.message);
        } finally {
            setLoading(false);
        }
    }

    return (
        <div className="login-page">
            <div className="login-wrap">
                <div className="panel">
                    <PanelHeader
                        title="Backoffice Authentication"
                        kicker="Sign in with your administrator account"
                        aside={
                            <button
                                type="button"
                                className="button"
                                style={{ minHeight: '30px', padding: '4px 10px', fontSize: '12px' }}
                                onClick={onToggleTheme}
                                title={`Switch to ${theme === 'dark' ? 'Light' : 'Dark'} mode`}
                            >
                                {theme === 'dark' ? <IconSun /> : <IconMoon />}
                            </button>
                        }
                    />
                    <form className="form-body" onSubmit={submit}>
                        <label className="field">
                            <span className="label">Email Address</span>
                            <input
                                className="input"
                                type="email"
                                value={email}
                                autoFocus
                                required
                                placeholder="admin@example.com"
                                onChange={(event) => setEmail(event.target.value)}
                            />
                        </label>
                        <label className="field">
                            <span className="label">Password</span>
                            <input
                                className="input"
                                type="password"
                                value={password}
                                required
                                placeholder="••••••••"
                                onChange={(event) => setPassword(event.target.value)}
                            />
                        </label>
                        {error && <div className="alert">{error}</div>}
                        <button className="button primary" type="submit" disabled={loading}>
                            {loading ? 'Authenticating...' : 'Sign In'}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    );
}

/* =========================================================================
   App Shell & Navigation
   ========================================================================= */
interface ShellProps {
    route: RouteInfo;
    user: User;
    theme: Theme;
    onToggleTheme: () => void;
    onLogout: () => void;
}

function Shell({ route, user, theme, onToggleTheme, onLogout }: ShellProps): React.JSX.Element {
    const activeResource = route.resource;

    async function logout() {
        await api('/api/admin/logout', { method: 'POST' }).catch(() => null);
        window.localStorage.removeItem(tokenKey);
        onLogout();
        navigate('/login');
    }

    return (
        <div className="shell">
            <header className="topbar">
                <div className="topbar-inner">
                    <button className="brand link-button" onClick={() => navigate('/dashboard')}>
                        <span className="brand-mark">MP</span>
                        <span className="brand-copy">
                            <span className="brand-title">Middleware Payment</span>
                            <span className="brand-subtitle">Unified Backoffice Console</span>
                        </span>
                    </button>
                    <div className="userbar">
                        {/* Theme Toggle Button */}
                        <button
                            type="button"
                            className="button"
                            onClick={onToggleTheme}
                            title={`Switch to ${theme === 'dark' ? 'Light' : 'Dark'} Mode`}
                        >
                            {theme === 'dark' ? <IconSun /> : <IconMoon />}
                            <span>{theme === 'dark' ? 'Light' : 'Dark'}</span>
                        </button>

                        <span className="user-chip">
                            <span className="avatar">{String(user?.name || 'A').slice(0, 1).toUpperCase()}</span>
                            <span>{user.name || 'Administrator'}</span>
                        </span>
                        <button className="button" onClick={logout}>
                            Logout
                        </button>
                    </div>
                </div>

                {/* Modern Sleek Navigation Tabs */}
                <nav className="tabs-nav" aria-label="Backoffice navigation">
                    <div className="tabs-container">
                        <button
                            className={`tab ${route.page === 'dashboard' ? 'active' : ''}`}
                            onClick={() => navigate('/dashboard')}
                        >
                            <IconDashboard />
                            <span>Dashboard</span>
                        </button>
                        {resources.map((item) => (
                            <button
                                className={`tab ${activeResource === item.key ? 'active' : ''}`}
                                key={item.key}
                                onClick={() => navigate(`/admin/${item.key}`)}
                            >
                                {getResourceIcon(item.key)}
                                <span>{item.label}</span>
                            </button>
                        ))}
                    </div>
                </nav>
            </header>

            <main className="content">
                {route.page === 'dashboard' && <DashboardPage />}
                {route.page === 'resource-index' && <ResourceIndex resource={route.resource} />}
                {route.page === 'resource-create' && <ResourceForm resource={route.resource} />}
                {route.page === 'resource-edit' && <ResourceForm resource={route.resource} id={route.id} />}
                {route.page === 'resource-show' && <ResourceShow resource={route.resource} id={route.id} />}
            </main>
        </div>
    );
}

function parseRoute(pathname: string): RouteInfo {
    if (pathname === '/' || pathname === '/dashboard') {
        return { page: 'dashboard' };
    }

    const match = pathname.match(/^\/admin\/([^/]+)(?:\/([^/]+))?(?:\/(edit))?$/);
    if (!match) {
        return { page: 'dashboard' };
    }

    const [, resourceRaw, id, edit] = match;
    const resource = resourceRaw as ResourceKey;

    if (!resourceDefinitions[resource]) {
        return { page: 'dashboard' };
    }

    if (id === 'create') {
        return { page: 'resource-create', resource };
    }

    if (id && edit) {
        return { page: 'resource-edit', resource, id };
    }

    if (id) {
        return { page: 'resource-show', resource, id };
    }

    return { page: 'resource-index', resource };
}

function App(): React.JSX.Element {
    const [pathname, setPathname] = useState<string>(window.location.pathname);
    const [authUser, setAuthUser] = useState<User | null>(null);
    const [checkingAuth, setCheckingAuth] = useState<boolean>(Boolean(storedToken()));
    const { theme, toggleTheme } = useTheme();
    const route = useMemo(() => parseRoute(pathname), [pathname]);

    useEffect(() => {
        const sync = () => setPathname(window.location.pathname);
        window.addEventListener('popstate', sync);

        return () => window.removeEventListener('popstate', sync);
    }, []);

    useEffect(() => {
        if (!storedToken()) {
            setCheckingAuth(false);
            return;
        }

        api<ApiPayload>('/api/admin/me')
            .then((data) => setAuthUser(data.user || null))
            .catch(() => {
                window.localStorage.removeItem(tokenKey);
                setAuthUser(null);
            })
            .finally(() => setCheckingAuth(false));
    }, []);

    if (checkingAuth) {
        return <div className="panel empty">Loading backoffice console...</div>;
    }

    if (!authUser) {
        return <LoginPage onLogin={setAuthUser} theme={theme} onToggleTheme={toggleTheme} />;
    }

    return (
        <Shell
            route={route}
            user={authUser}
            theme={theme}
            onToggleTheme={toggleTheme}
            onLogout={() => setAuthUser(null)}
        />
    );
}

const root = document.getElementById('backoffice-root');
if (root) {
    createRoot(root).render(<App />);
}
