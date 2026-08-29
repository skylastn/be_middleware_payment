export interface PaymentGatewayItem {
    id: string | number;
    key: string;
    name: string;
    description?: string;
    created_at?: string;
    updated_at?: string;
}

export interface PaymentGatewayListResponse {
    status?: boolean;
    code?: number;
    message?: string;
    total?: number;
    perPage?: number;
    currentPage?: number;
    data: PaymentGatewayItem[];
}
