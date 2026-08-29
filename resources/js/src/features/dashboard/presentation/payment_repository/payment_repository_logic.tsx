import { useEffect, useState } from 'react';
import { resourceDefinitions } from '@/features/resource/domain/constant/resource_definitions';
import { ResourceService } from '@/features/resource/application/resource_service';
import { dataItems, dataRecord, navigate } from '@/shared/utils/format_utils';

export interface UsePaymentRepositoryLogicProps {
    mode: 'resource-index' | 'resource-create' | 'resource-edit' | 'resource-show';
    id?: string | number;
}

export function usePaymentRepositoryLogic({ mode, id }: UsePaymentRepositoryLogicProps) {
    const definition = resourceDefinitions['payment-repositories'];
    const isEdit = mode === 'resource-edit';

    const [payload, setPayload] = useState<any>(null);
    const [record, setRecord] = useState<any | null>(null);
    const [form, setForm] = useState<Record<string, any>>({
        payment_gateway_id: '',
        key: '',
        mode: 'sandbox',
        value: '{}',
    });
    const [jsonError, setJsonError] = useState<string>('');
    const [loading, setLoading] = useState<boolean>(true);
    const [saving, setSaving] = useState<boolean>(false);
    const [error, setError] = useState<string>('');
    const [gateways, setGateways] = useState<Array<{ id: string | number; name: string; key: string }>>([]);

    // Filter states
    const [searchTerm, setSearchTerm] = useState<string>('');
    const [selectedMode, setSelectedMode] = useState<string>('all');
    const [page, setPage] = useState<number>(1);
    const [perPage, setPerPage] = useState<number>(15);

    useEffect(() => {
        ResourceService.list('/api/admin/payment-gateways?per_page=100')
            .then((res: any) => {
                const items = dataItems(res) || [];
                setGateways(items.map((g: any) => ({ id: g.id, name: g.name || g.key, key: g.key })));
            })
            .catch(() => {});
    }, []);

    const loadList = async (pageNum = page) => {
        setLoading(true);
        setError('');
        try {
            const query = new URLSearchParams();
            if (pageNum > 1) query.set('page', String(pageNum));
            if (perPage !== 15) query.set('per_page', String(perPage));
            if (searchTerm.trim()) query.set('search', searchTerm.trim());
            if (selectedMode !== 'all') query.set('mode', selectedMode);

            const queryString = query.toString();
            const url = `${definition.endpoints.list}${queryString ? `?${queryString}` : ''}`;
            const res = await ResourceService.list(url);
            setPayload(res);
            setPage(pageNum);
        } catch (err: any) {
            setError(err?.message || 'Failed to load payment repositories.');
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
            const itemData = dataRecord(res);
            setRecord(itemData);
            if (isEdit) {
                const valStr = typeof itemData.value === 'object' ? JSON.stringify(itemData.value, null, 2) : (itemData.value || '{}');
                setForm({
                    payment_gateway_id: itemData.payment_gateway_id || '',
                    key: itemData.key || '',
                    mode: itemData.mode || 'sandbox',
                    value: valStr,
                });
            }
        } catch (err: any) {
            setError(err?.message || 'Failed to load repository details.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (mode === 'resource-index') {
            loadList(1);
        } else if (mode === 'resource-edit' || mode === 'resource-show') {
            loadItem();
        } else if (mode === 'resource-create') {
            setLoading(false);
        }
    }, [mode, id, selectedMode, perPage]);

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        loadList(1);
    };

    const handleClearSearch = () => {
        setSearchTerm('');
        setTimeout(() => loadList(1), 0);
    };

    const handleFormSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);
        setError('');
        setJsonError('');

        // Parse JSON value
        let parsedValue = form.value;
        try {
            if (typeof form.value === 'string' && (form.value.trim().startsWith('{') || form.value.trim().startsWith('['))) {
                parsedValue = JSON.parse(form.value);
            }
        } catch (err: any) {
            setJsonError('Invalid JSON format in Value field: ' + err.message);
            setSaving(false);
            return;
        }

        try {
            const submitData = {
                ...form,
                value: parsedValue,
            };
            if (isEdit && id && definition.endpoints.update) {
                await ResourceService.update(definition.endpoints.update(id), submitData);
            } else if (definition.endpoints.create) {
                await ResourceService.create(definition.endpoints.create, submitData);
            }
            navigate('/admin/payment-repositories');
        } catch (err: any) {
            setError(err?.message || 'Failed to save payment repository.');
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async (repoId: string | number) => {
        if (!definition.endpoints.delete) return;
        if (!window.confirm(`Are you sure you want to delete payment repository #${repoId}?`)) return;
        try {
            await ResourceService.delete(definition.endpoints.delete(repoId));
            loadList(page);
        } catch (err: any) {
            setError(err?.message || 'Failed to delete payment repository.');
        }
    };

    const records = dataItems(payload);
    const total = Number(payload?.total ?? records.length);
    const currentPage = Number(payload?.currentPage ?? page);
    const currentPerPage = Number(payload?.perPage ?? perPage);

    return {
        definition,
        isEdit,
        payload,
        records,
        record,
        form,
        setForm,
        gateways,
        jsonError,
        loading,
        saving,
        error,
        searchTerm,
        setSearchTerm,
        selectedMode,
        setSelectedMode,
        page,
        perPage,
        setPerPage,
        total,
        currentPage,
        currentPerPage,
        loadList,
        handleSearchSubmit,
        handleClearSearch,
        handleFormSubmit,
        handleDelete,
    };
}
