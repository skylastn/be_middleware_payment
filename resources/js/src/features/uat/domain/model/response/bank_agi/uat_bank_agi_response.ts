export interface UatGenerateQrResponse {
    reference: string;
    qr_string: string | null;
    result?: any;
}

export interface UatQueryStatusResponse {
    reference: string;
    bill_number: string | null;
    amount: number;
    local_status: string | null;
    expired_at: string | null;
    bank_status: any;
}

export interface UatNotifyResponse {
    notify_response: any;
    local_status: string | null;
}

export interface UatProbeBankResponse {
    case: string;
    url: string;
    request_headers: Record<string, string>;
    request_body: any;
    bank_response: any;
    response_code: string | null;
    response_message: string | null;
}
