import { PaymentCategoryItem } from './payment_category_response';
import { PaymentGatewayItem } from './payment_gateway_response';

export interface PaymentMethodItem {
    id: string | number;
    key: string;
    name: string;
    category_id?: number | string | null;
    type?: string;
    payment_gateway_id?: string | null;
    gateway?: PaymentGatewayItem | null;
    payment_gateway?: PaymentGatewayItem | null;
    bankCode?: string;
    image?: string | null;
    image_url?: string | null;
    is_active?: boolean;
    category?: PaymentCategoryItem | null;
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
