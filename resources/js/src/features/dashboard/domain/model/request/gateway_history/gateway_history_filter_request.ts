export interface GatewayHistoryFilterRequest {
    payment_repository_id: string | number;
    per_page?: number;
    start_date?: string;
    end_date?: string;
}
