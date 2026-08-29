export interface PaymentCategoryItem {
    id: string | number;
    key: string;
    title: string;
    detail?: string;
    created_at?: string;
    updated_at?: string;
}

export interface PaymentCategoryListResponse {
    status?: boolean;
    code?: number;
    message?: string;
    total?: number;
    perPage?: number;
    currentPage?: number;
    data: PaymentCategoryItem[];
}

export interface PaymentCategoryFilterRequest {
    page?: number;
    per_page?: number;
    search?: string;
}
