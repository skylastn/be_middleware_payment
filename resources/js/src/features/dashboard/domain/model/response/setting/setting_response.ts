export interface SettingItem {
    id: string | number;
    key: string;
    value: string;
    created_at?: string;
    updated_at?: string;
}

export interface SettingListResponse {
    status?: boolean;
    code?: number;
    message?: string;
    total?: number;
    perPage?: number;
    currentPage?: number;
    data: SettingItem[];
}
