export interface PaymentRepositoryItem {
    id: string | number;
    payment_gateway_id: string | number;
    key?: string;
    mode: string;
    value: any;
    payment_gateway?: {
        id: string | number;
        name: string;
        key: string;
    };
    created_at?: string;
    updated_at?: string;
}

export interface PaymentRepositoryListResponse {
    status?: boolean;
    code?: number;
    message?: string;
    total?: number;
    perPage?: number;
    currentPage?: number;
    data: PaymentRepositoryItem[];
}

export interface PaymentRepositoryFilterRequest {
    page?: number;
    per_page?: number;
    search?: string;
    mode?: string;
}
