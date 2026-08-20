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

export type RouteInfo =
    | { page: 'dashboard'; resource?: undefined; id?: undefined }
    | { page: 'resource-index'; resource: ResourceKey; id?: undefined }
    | { page: 'resource-create'; resource: ResourceKey; id?: undefined }
    | { page: 'resource-edit'; resource: ResourceKey; id: string }
    | { page: 'resource-show'; resource: ResourceKey; id: string };
