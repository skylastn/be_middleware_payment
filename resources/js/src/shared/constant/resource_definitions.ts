import { ResourceDefinition, ResourceKey } from '../../model/resource_model';

export const resourceDefinitions: Record<ResourceKey, ResourceDefinition> = {
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

export const resourceList: Array<{ key: ResourceKey; label: string }> = (
    Object.entries(resourceDefinitions) as [ResourceKey, ResourceDefinition][]
).map(([key, definition]) => ({
    key,
    label: definition.label,
}));
