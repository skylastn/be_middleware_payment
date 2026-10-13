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
    email?: string;
    hour?: string | number;
    start_time?: string | number;
    end_time?: string | number;
}
