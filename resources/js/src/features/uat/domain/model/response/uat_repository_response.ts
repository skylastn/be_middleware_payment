export interface UatRepositoryItem {
    id: string;
    key: string;
    mode: string;
    gateway_name: string;
    updated_at: string;
}

export interface UatRepositoryFilterRequest {
    mode?: string;
}
