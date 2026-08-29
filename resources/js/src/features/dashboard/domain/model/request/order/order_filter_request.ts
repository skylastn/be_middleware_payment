export interface OrderFilterRequest {
    page?: number;
    per_page?: number;
    search?: string;
    mode?: string;
    status?: string;
    start_date?: string;
    end_date?: string;
    payment_repository_id?: string;
    type?: string;
}
