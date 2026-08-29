import { useEffect, useState } from 'react';
import { resourceDefinitions } from '@/features/resource/domain/constant/resource_definitions';
import { ResourceService } from '@/features/resource/application/resource_service';
import { dataItems, dataRecord } from '@/shared/utils/format_utils';

export interface UseOrderLogicProps {
    mode: 'resource-index' | 'resource-create' | 'resource-edit' | 'resource-show';
    id?: string | number;
}

export function useOrderLogic({ mode, id }: UseOrderLogicProps) {
    const definition = resourceDefinitions.orders;
    const [payload, setPayload] = useState<any>(null);
    const [record, setRecord] = useState<any | null>(null);
    const [loading, setLoading] = useState<boolean>(true);
    const [error, setError] = useState<string>('');
    const [notice, setNotice] = useState<string>('');
    const [resending, setResending] = useState<boolean>(false);

    // Filters
    const [searchTerm, setSearchTerm] = useState<string>('');
    const [page, setPage] = useState<number>(1);
    const [perPage, setPerPage] = useState<number>(10);
    const [selectedMode, setSelectedMode] = useState<string>('all');
    const [selectedStatus, setSelectedStatus] = useState<string>('all');
    const [startDate, setStartDate] = useState<string>('');
    const [endDate, setEndDate] = useState<string>('');
    const [selectedRepository, setSelectedRepository] = useState<string>('all');
    const [repositories, setRepositories] = useState<Array<{ id: string | number; label: string }>>([]);

    useEffect(() => {
        if (mode === 'resource-index') {
            ResourceService.list('/api/admin/payment-repositories?per_page=100')
                .then((res: any) => {
                    const items = dataItems(res) || [];
                    setRepositories(
                        items.map((repo: any) => ({
                            id: repo.id,
                            label: `${repo.key || repo.payment_gateway?.name || 'Repository'} (${repo.mode || 'sandbox'}) - #${repo.id}`,
                        }))
                    );
                })
                .catch(() => {});
        }
    }, [mode]);

    const loadList = async (pageNum = page) => {
        setLoading(true);
        setError('');
        try {
            const query = new URLSearchParams();
            if (pageNum > 1) query.set('page', String(pageNum));
            if (perPage !== 10) query.set('per_page', String(perPage));
            if (searchTerm.trim()) query.set('search', searchTerm.trim());
            if (selectedMode !== 'all') query.set('mode', selectedMode);
            if (selectedStatus !== 'all') query.set('status', selectedStatus);
            if (startDate) query.set('start_date', startDate);
            if (endDate) query.set('end_date', endDate);
            if (selectedRepository !== 'all') query.set('payment_repository_id', selectedRepository);

            const queryString = query.toString();
            const url = `${definition.endpoints.list}${queryString ? `?${queryString}` : ''}`;
            const res = await ResourceService.list(url);
            setPayload(res);
            setPage(pageNum);
        } catch (err: any) {
            setError(err?.message || 'Failed to load orders.');
        } finally {
            setLoading(false);
        }
    };

    const loadItem = async () => {
        if (!id || !definition.endpoints.show) return;
        setLoading(true);
        setError('');
        try {
            const url = definition.endpoints.show(id);
            const res = await ResourceService.show(url);
            setRecord(dataRecord(res));
        } catch (err: any) {
            setError(err?.message || 'Failed to load order details.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (mode === 'resource-index') {
            loadList(1);
        } else if (mode === 'resource-show') {
            loadItem();
        }
    }, [mode, id, selectedMode, selectedStatus, startDate, endDate, selectedRepository, perPage]);

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        loadList(1);
    };

    const handleClearSearch = () => {
        setSearchTerm('');
        setStartDate('');
        setEndDate('');
        setSelectedRepository('all');
        setSelectedMode('all');
        setSelectedStatus('all');
    };

    const handleResendCallback = async (orderId: string | number) => {
        if (!definition.endpoints.resend) return;
        setResending(true);
        setNotice('');
        setError('');
        try {
            await ResourceService.resendCallback(definition.endpoints.resend(orderId));
            setNotice(`Callback for order #${orderId} was resent successfully.`);
            if (mode === 'resource-show') {
                loadItem();
            }
        } catch (err: any) {
            setError(err?.message || 'Failed to resend callback.');
        } finally {
            setResending(false);
        }
    };

    const records = dataItems(payload);
    const total = Number(payload?.total ?? records.length);
    const currentPage = Number(payload?.currentPage ?? page);
    const currentPerPage = Number(payload?.perPage ?? perPage);

    return {
        definition,
        payload,
        records,
        record,
        loading,
        error,
        notice,
        resending,
        searchTerm,
        setSearchTerm,
        page,
        perPage,
        setPerPage,
        total,
        currentPage,
        currentPerPage,
        selectedMode,
        setSelectedMode,
        selectedStatus,
        setSelectedStatus,
        startDate,
        setStartDate,
        endDate,
        setEndDate,
        selectedRepository,
        setSelectedRepository,
        repositories,
        loadList,
        loadItem,
        handleSearchSubmit,
        handleClearSearch,
        handleResendCallback,
    };
}
