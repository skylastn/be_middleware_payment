import { PaymentCategoryItem } from './payment_category_response';

export interface PaymentMethodItem {
    id: string | number;
    key: string;
    name: string;
    category_id?: number | string | null;
    type?: string;
    from?: string;
    bankCode?: string;
    value?: string;
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
