import { PaymentGatewayItem } from './payment_gateway_response';

export interface PaymentRepositoryItem {
    id: string | number;
    payment_gateway_id: string | number;
    key?: string;
    mode: string;
    value: any;
    payment_gateway?: PaymentGatewayItem;
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
