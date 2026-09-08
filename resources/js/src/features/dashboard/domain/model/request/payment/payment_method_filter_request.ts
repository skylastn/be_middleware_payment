export interface PaymentMethodFilterRequest {
    page?: number;
    per_page?: number;
    search?: string;
    payment_gateway_id?: string;
    is_active?: boolean | string;
}
