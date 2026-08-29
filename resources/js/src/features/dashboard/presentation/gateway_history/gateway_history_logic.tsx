import { useEffect, useState } from 'react';
import { ResourceService } from '@/features/resource/application/resource_service';
import { apiClient } from '@/shared/network/api_client';
import { dataItems, getMonthRange, getLastDaysRange, getTodayDateString } from '@/shared/utils/format_utils';

export interface GatewayHistoryItem {
    id: string;
    reference: string;
    amount: number;
    currency: string;
    status: string;
    payment_method: string;
    customer: string;
    created_at?: string;
    raw?: any;
}

export interface GatewayHistoryPayload {
    gateway: string;
    has_more: boolean;
    total: number;
    items: GatewayHistoryItem[];
    repository?: {
        id: string;
        key: string;
        gateway: string;
        gateway_key: string;
        mode: string;
    };
    message?: string;
}

export function useGatewayHistoryLogic() {
    const [payload, setPayload] = useState<GatewayHistoryPayload | null>(null);
    const [loading, setLoading] = useState<boolean>(true);
    const [error, setError] = useState<string>('');
    const [repositories, setRepositories] = useState<Array<{ id: string | number; label: string; gatewayId?: string }>>([]);
    const [selectedRepository, setSelectedRepository] = useState<string>('');
    const [selectedItem, setSelectedItem] = useState<GatewayHistoryItem | null>(null);

    // Filters
    const [startDate, setStartDate] = useState<string>('');
    const [endDate, setEndDate] = useState<string>('');
    const [perPage, setPerPage] = useState<number>(10);

    const loadRepositories = async () => {
        try {
            const res = await ResourceService.list('/api/admin/payment-repositories?per_page=100');
            const items = dataItems(res) || [];
            const mapped = items.map((r: any) => ({
                id: String(r.id),
                gatewayId: r.payment_gateway_id,
                label: `${r.payment_gateway?.name || r.key || 'Repository'} (${r.mode || 'sandbox'}) - #${r.id}`,
            }));
            setRepositories(mapped);
            if (mapped.length > 0 && !selectedRepository) {
                setSelectedRepository(String(mapped[0].id));
            }
        } catch {
            // Repositories fetch error handled silently
        }
    };

    const fetchHistory = async () => {
        if (!selectedRepository) return;
        setLoading(true);
        setError('');
        try {
            const query = new URLSearchParams();
            query.set('payment_repository_id', selectedRepository);
            query.set('per_page', String(perPage));
            if (startDate) query.set('start_date', startDate);
            if (endDate) query.set('end_date', endDate);

            const res = await apiClient<any>(`/api/admin/gateway-history?${query.toString()}`);
            setPayload(res?.data || res);
        } catch (err: any) {
            setError(err?.message || 'Failed to fetch live history from gateway.');
            setPayload(null);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        loadRepositories();
    }, []);

    useEffect(() => {
        if (selectedRepository) {
            fetchHistory();
        }
    }, [selectedRepository, startDate, endDate, perPage]);

    const handleClearFilters = () => {
        setStartDate('');
        setEndDate('');
    };

    return {
        payload,
        loading,
        error,
        repositories,
        selectedRepository,
        setSelectedRepository,
        selectedItem,
        setSelectedItem,
        startDate,
        setStartDate,
        endDate,
        setEndDate,
        perPage,
        setPerPage,
        handleClearFilters,
        reload: fetchHistory,
    };
}
