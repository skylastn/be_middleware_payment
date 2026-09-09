import { ResourceDefinition, ResourceKey } from '../model/resource_model';

export const resourceDefinitions: Record<ResourceKey, ResourceDefinition> = {
    orders: {
        label: 'Orders',
        singular: 'Order',
        readonly: true,
        columns: ['reference', 'type', 'payment_method', 'amount', 'status', 'mode', 'created_at'],
        fields: {},
        endpoints: {
            list: '/api/admin/orders',
            show: (id: string | number) => `/api/admin/orders/${id}`,
            resend: (id: string | number) => `/api/admin/orders/${id}/resend-callback`,
            setSuccess: (id: string | number) => `/api/admin/orders/${id}/set-success`,
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
            list: '/api/admin/projects',
            show: (id: string | number) => `/api/admin/projects/${id}`,
            create: '/api/admin/projects/create',
            update: (id: string | number) => `/api/admin/projects/${id}`,
            delete: (id: string | number) => `/api/admin/projects/${id}`,
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
            list: '/api/admin/payment-gateways',
            show: (id: string | number) => `/api/admin/payment-gateways/${id}`,
            create: '/api/admin/payment-gateways/create',
            update: (id: string | number) => `/api/admin/payment-gateways/${id}`,
            delete: (id: string | number) => `/api/admin/payment-gateways/${id}`,
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
            list: '/api/admin/payment-repositories',
            show: (id: string | number) => `/api/admin/payment-repositories/${id}`,
            create: '/api/admin/payment-repositories/create',
            update: (id: string | number) => `/api/admin/payment-repositories/${id}`,
            delete: (id: string | number) => `/api/admin/payment-repositories/${id}`,
            testOrder: (id: string | number) => `/api/admin/payment-repositories/${id}/test-order`,
        },
    },
    'payment-methods': {
        label: 'Payment Methods',
        singular: 'Payment Method',
        columns: ['key', 'name', 'type', 'payment_gateway_id', 'bankCode', 'is_active'],
        fields: {
            key: { type: 'text', required: true },
            name: { type: 'text', required: true },
            type: { type: 'text', required: true },
            payment_gateway_id: { label: 'Payment Gateway', type: 'select', required: true },
            bankCode: { label: 'Bank Code', type: 'text' },
            is_active: { label: 'Active in Client', type: 'boolean' },
        },
        endpoints: {
            list: '/api/admin/payment-methods',
            show: (id: string | number) => `/api/admin/payment-methods/${id}`,
            create: '/api/admin/payment-methods/create',
            update: (id: string | number) => `/api/admin/payment-methods/${id}`,
            delete: (id: string | number) => `/api/admin/payment-methods/${id}`,
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
            list: '/api/admin/payment-categories',
            show: (id: string | number) => `/api/admin/payment-categories/${id}`,
            create: '/api/admin/payment-categories/create',
            update: (id: string | number) => `/api/admin/payment-categories/${id}`,
            delete: (id: string | number) => `/api/admin/payment-categories/${id}`,
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
            list: '/api/admin/settings',
            show: (id: string | number) => `/api/admin/settings/${id}`,
            create: '/api/admin/settings/create',
            update: (id: string | number) => `/api/admin/settings/${id}`,
            delete: (id: string | number) => `/api/admin/settings/${id}`,
        },
    },
};

export const resourceList: Array<{ key: ResourceKey; label: string }> = (
    Object.entries(resourceDefinitions) as [ResourceKey, ResourceDefinition][]
).map(([key, definition]) => ({
    key,
    label: definition.label,
}));
