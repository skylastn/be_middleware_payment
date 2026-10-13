export interface OrderStatusBreakdown {
    count: number;
    amount: number;
}

export interface OrderSummaryTotals {
    total_orders: number;
    total_amount: number;
    average_amount: number;
    status_breakdown: Record<string, OrderStatusBreakdown>;
}
