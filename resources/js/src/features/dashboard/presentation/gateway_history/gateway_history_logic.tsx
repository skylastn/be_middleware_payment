import { useEffect, useState } from 'react';
import { gatewayHistoryService } from '../../application/gateway_history_service';
import { paymentRepositoryService } from '../../application/payment_repository_service';
import { GatewayHistoryItem, GatewayHistoryPayload } from '../../domain/model/gateway_history/gateway_history_model';

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
            const res = await paymentRepositoryService.getPaymentRepositories({ per_page: 100 });
            const items = res?.data || [];
            const mapped = items.map((r: any) => ({
                id: String(r.id),
                gatewayId: String(r.payment_gateway_id),
                label: `${r.payment_gateway?.name || r.key || 'Repository'} (${r.mode || 'sandbox'}) - #${r.id}`,
            }));
            setRepositories(mapped);
            if (mapped.length > 0 && !selectedRepository) {
                setSelectedRepository(String(mapped[0].id));
            }
        } catch {
            // Error handled gracefully
        }
    };

    const fetchHistory = async () => {
        if (!selectedRepository) return;
        setLoading(true);
        setError('');
        try {
            const res = await gatewayHistoryService.getGatewayHistory({
                payment_repository_id: selectedRepository,
                per_page: perPage,
                start_date: startDate || undefined,
                end_date: endDate || undefined,
            });
            setPayload(res);
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
