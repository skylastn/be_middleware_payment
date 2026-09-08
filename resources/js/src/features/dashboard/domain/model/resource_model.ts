export type ResourceKey =
    | 'orders'
    | 'projects'
    | 'payment-gateways'
    | 'payment-repositories'
    | 'payment-methods'
    | 'payment-categories'
    | 'settings';

export type FieldType = 'text' | 'select' | 'textarea' | 'json' | 'boolean';

export interface FieldDefinition {
    label?: string;
    type?: FieldType;
    required?: boolean;
    options?: string[];
    default?: any;
}

export interface ResourceEndpoints {
    list: string;
    show?: (id: string | number) => string;
    create?: string;
    update?: (id: string | number) => string;
    delete?: (id: string | number) => string;
    resend?: (id: string | number) => string;
    setSuccess?: (id: string | number) => string;
    testOrder?: (id: string | number) => string;
}

export interface ResourceDefinition {
    label: string;
    singular: string;
    readonly?: boolean;
    columns: string[];
    fields: Record<string, FieldDefinition>;
    readonlyFields?: Record<string, FieldDefinition>;
    endpoints: ResourceEndpoints;
}

export type Theme = 'dark' | 'light';

export interface RouteInfo {
    page: 'dashboard' | 'logs' | 'gateway-history' | 'project-logs' | 'resource-index' | 'resource-create' | 'resource-edit' | 'resource-show';
    resource?: ResourceKey;
    id?: string | number;
}
