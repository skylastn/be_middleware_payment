export interface GatewayHistoryItem {
    id: string;
    reference: string;
    amount: number;
    currency: string;
    status: string;
    payment_method: string;
    customer: string;
    created_at?: string;
    raw?: any;
}

export interface GatewayHistoryPayload {
    gateway: string;
    has_more: boolean;
    total: number;
    items: GatewayHistoryItem[];
    repository?: {
        id: string;
        key: string;
        gateway: string;
        gateway_key: string;
        mode: string;
    };
    message?: string;
}

export interface GatewayHistoryFilterRequest {
    payment_repository_id: string | number;
    per_page?: number;
    start_date?: string;
    end_date?: string;
}
