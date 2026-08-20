import { useEffect, useState } from 'react';
import { ResourceKey } from '@/features/resource/domain/model/resource_model';
import { resourceDefinitions } from '@/features/resource/domain/constant/resource_definitions';
import { ResourceService } from '@/features/resource/application/resource_service';
import { dataItems } from '@/shared/utils/format_utils';

export interface UseResourceIndexLogicProps {
    resource: ResourceKey;
}

export function useResourceIndexLogic({ resource }: UseResourceIndexLogicProps) {
    const definition = resourceDefinitions[resource];
    const [payload, setPayload] = useState<any>(null);
    const [loading, setLoading] = useState<boolean>(true);
    const [error, setError] = useState<string>('');

    // API Filter & Pagination States
    const [searchTerm, setSearchTerm] = useState<string>('');
    const [selectedMode, setSelectedMode] = useState<string>('all');
    const [selectedStatus, setSelectedStatus] = useState<string>('all');
    const [page, setPage] = useState<number>(1);
    const [perPage, setPerPage] = useState<number>(15);

    const fetchRecords = async (
        targetPage = page,
        querySearch = searchTerm,
        mode = selectedMode,
        status = selectedStatus,
        itemsPerPage = perPage
    ) => {
        setLoading(true);
        setError('');
        try {
            const query = new URLSearchParams();
            if (targetPage > 1) query.set('page', String(targetPage));
            if (itemsPerPage !== 15) query.set('per_page', String(itemsPerPage));
            if (querySearch.trim()) query.set('search', querySearch.trim());
            if (mode !== 'all') query.set('mode', mode);
            if (status !== 'all') query.set('status', status);

            const queryString = query.toString();
            const url = `${definition.endpoints.list}${queryString ? `?${queryString}` : ''}`;
            const res = await ResourceService.list(url);
            setPayload(res);
            setPage(targetPage);
        } catch (err: any) {
            setError(err?.message || 'Failed to load records.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        setPayload(null);
        setSearchTerm('');
        setSelectedMode('all');
        setSelectedStatus('all');
        setPage(1);
        fetchRecords(1, '', 'all', 'all', perPage);
    }, [resource]);

    const handleModeChange = (newMode: string) => {
        setSelectedMode(newMode);
        fetchRecords(1, searchTerm, newMode, selectedStatus, perPage);
    };

    const handleStatusChange = (newStatus: string) => {
        setSelectedStatus(newStatus);
        fetchRecords(1, searchTerm, selectedMode, newStatus, perPage);
    };

    const handlePerPageChange = (newPerPage: number) => {
        setPerPage(newPerPage);
        fetchRecords(1, searchTerm, selectedMode, selectedStatus, newPerPage);
    };

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        fetchRecords(1, searchTerm, selectedMode, selectedStatus, perPage);
    };

    const handleClearFilters = () => {
        setSearchTerm('');
        setSelectedMode('all');
        setSelectedStatus('all');
        fetchRecords(1, '', 'all', 'all', perPage);
    };

    const records = dataItems(payload);
    const total = Number(payload?.total ?? records.length);
    const currentPage = Number(payload?.currentPage ?? page);
    const currentPerPage = Number(payload?.perPage ?? perPage);
    const lastPage = Math.max(1, Math.ceil(total / Math.max(currentPerPage, 1)));
    const isFiltered = searchTerm.trim() !== '' || selectedMode !== 'all' || selectedStatus !== 'all';

    async function destroy(record: any) {
        if (!definition.endpoints.delete) return;
        if (!window.confirm(`Are you sure you want to delete ${definition.singular} #${record.id || record.key}?`)) {
            return;
        }

        await ResourceService.delete(definition.endpoints.delete(record.id));
        fetchRecords(page);
    }

    async function resend(record: any) {
        if (!definition.endpoints.resend) return;
        if (!window.confirm(`Resend merchant callback for order ${record.reference}?`)) {
            return;
        }

        await ResourceService.resendCallback(definition.endpoints.resend(record.id));
        fetchRecords(page);
    }

    return {
        definition,
        payload,
        records,
        loading,
        error,
        searchTerm,
        setSearchTerm,
        selectedMode,
        selectedStatus,
        page,
        perPage,
        total,
        currentPage,
        currentPerPage,
        lastPage,
        isFiltered,
        fetchRecords,
        handleModeChange,
        handleStatusChange,
        handlePerPageChange,
        handleSearchSubmit,
        handleClearFilters,
        destroy,
        resend,
    };
}
