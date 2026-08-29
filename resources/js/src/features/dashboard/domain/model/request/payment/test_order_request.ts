export interface TestOrderRequest {
    amount: number;
    currency?: string;
    email?: string;
    name?: string;
    paymentMethod?: string;
}
