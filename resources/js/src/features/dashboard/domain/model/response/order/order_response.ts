export interface OrderItem {
    id: string;
    payment_repository_id?: string;
    mode: string;
    type: string;
    reference: string;
    name?: string | null;
    payment_method: string;
    amount?: number | string | null;
    value?: string | null;
    status: string;
    url?: string;
    return_url?: string | null;
    notes?: string;
    address?: string;
    phone?: string;
    email?: string;
    request?: string;
    response?: string;
    callback?: string;
    payment_methods?: any;
    payment_repository?: any;
    project?: any;
    histories?: any[];
    created_at?: string;
    updated_at?: string;
}

export interface OrderListResponse {
    status?: boolean;
    code?: number;
    message?: string;
    total?: number;
    perPage?: number;
    currentPage?: number;
    data: OrderItem[];
}
