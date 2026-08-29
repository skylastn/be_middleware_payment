export interface PaymentMethodItem {
    id: string | number;
    key: string;
    name: string;
    type?: string;
    from?: string;
    bankCode?: string;
    value?: string;
    created_at?: string;
    updated_at?: string;
}

export interface PaymentMethodListResponse {
    status?: boolean;
    code?: number;
    message?: string;
    total?: number;
    perPage?: number;
    currentPage?: number;
    data: PaymentMethodItem[];
}
