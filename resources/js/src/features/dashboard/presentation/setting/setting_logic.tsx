import { useEffect, useState } from 'react';
import { resourceDefinitions } from '@/features/resource/domain/constant/resource_definitions';
import { ResourceService } from '@/features/resource/application/resource_service';
import { dataItems, dataRecord, navigate } from '@/shared/utils/format_utils';

export interface UseSettingLogicProps {
    mode: 'resource-index' | 'resource-create' | 'resource-edit' | 'resource-show';
    id?: string | number;
}

export function useSettingLogic({ mode, id }: UseSettingLogicProps) {
    const definition = resourceDefinitions.settings;
    const isEdit = mode === 'resource-edit';

    const [payload, setPayload] = useState<any>(null);
    const [record, setRecord] = useState<any | null>(null);
    const [form, setForm] = useState<Record<string, any>>({
        key: '',
        value: '',
    });
    const [loading, setLoading] = useState<boolean>(true);
    const [saving, setSaving] = useState<boolean>(false);
    const [error, setError] = useState<string>('');

    // Filter states
    const [searchTerm, setSearchTerm] = useState<string>('');
    const [page, setPage] = useState<number>(1);
    const [perPage, setPerPage] = useState<number>(10);

    const loadList = async (pageNum = page) => {
        setLoading(true);
        setError('');
        try {
            const query = new URLSearchParams();
            if (pageNum > 1) query.set('page', String(pageNum));
            if (perPage !== 10) query.set('per_page', String(perPage));
            if (searchTerm.trim()) query.set('search', searchTerm.trim());

            const queryString = query.toString();
            const url = `${definition.endpoints.list}${queryString ? `?${queryString}` : ''}`;
            const res = await ResourceService.list(url);
            setPayload(res);
            setPage(pageNum);
        } catch (err: any) {
            setError(err?.message || 'Failed to load settings.');
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
                setForm({
                    key: itemData.key || '',
                    value: typeof itemData.value === 'object' ? JSON.stringify(itemData.value, null, 2) : (itemData.value || ''),
                });
            }
        } catch (err: any) {
            setError(err?.message || 'Failed to load setting details.');
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
    }, [mode, id, perPage]);

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
        try {
            if (isEdit && id && definition.endpoints.update) {
                await ResourceService.update(definition.endpoints.update(id), form);
            } else if (definition.endpoints.create) {
                await ResourceService.create(definition.endpoints.create, form);
            }
            navigate('/admin/settings');
        } catch (err: any) {
            setError(err?.message || 'Failed to save setting.');
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async (settingId: string | number) => {
        if (!definition.endpoints.delete) return;
        if (!window.confirm(`Are you sure you want to delete setting #${settingId}?`)) return;
        try {
            await ResourceService.delete(definition.endpoints.delete(settingId));
            loadList(page);
        } catch (err: any) {
            setError(err?.message || 'Failed to delete setting.');
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
        loading,
        saving,
        error,
        searchTerm,
        setSearchTerm,
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
