export interface UatOrderItem {
    reference: string;
    bill_number: string | null;
    amount: number;
    status: string | null;
    mode: string | null;
    created_at: string;
}
