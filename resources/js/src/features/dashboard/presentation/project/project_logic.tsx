import { useEffect, useState } from 'react';
import { resourceDefinitions } from '@/features/resource/domain/constant/resource_definitions';
import { ResourceService } from '@/features/resource/application/resource_service';
import { apiClient } from '@/shared/network/api_client';
import { dataItems, dataRecord, navigate } from '@/shared/utils/format_utils';

export interface UseProjectLogicProps {
    mode: 'resource-index' | 'resource-create' | 'resource-edit' | 'resource-show';
    id?: string | number;
}

export function useProjectLogic({ mode, id }: UseProjectLogicProps) {
    const definition = resourceDefinitions.projects;
    const isEdit = mode === 'resource-edit';

    const [payload, setPayload] = useState<any>(null);
    const [record, setRecord] = useState<any | null>(null);
    const [form, setForm] = useState<Record<string, any>>({
        name: '',
        type: '',
        slug: 'duitku',
        callback: '',
    });
    const [loading, setLoading] = useState<boolean>(true);
    const [saving, setSaving] = useState<boolean>(false);
    const [syncing, setSyncing] = useState<boolean>(false);
    const [error, setError] = useState<string>('');
    const [notice, setNotice] = useState<string>('');

    // Filter states
    const [searchTerm, setSearchTerm] = useState<string>('');
    const [selectedSlug, setSelectedSlug] = useState<string>('all');
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
            if (selectedSlug !== 'all') query.set('slug', selectedSlug);

            const queryString = query.toString();
            const url = `${definition.endpoints.list}${queryString ? `?${queryString}` : ''}`;
            const res = await ResourceService.list(url);
            setPayload(res);
            setPage(pageNum);
        } catch (err: any) {
            setError(err?.message || 'Failed to load projects.');
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
                    name: itemData.name || '',
                    type: itemData.type || '',
                    slug: itemData.slug || 'duitku',
                    callback: itemData.callback || '',
                });
            }
        } catch (err: any) {
            setError(err?.message || 'Failed to load project details.');
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
    }, [mode, id, selectedSlug, perPage]);

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
        setNotice('');
        try {
            if (isEdit && id && definition.endpoints.update) {
                await ResourceService.update(definition.endpoints.update(id), form);
            } else if (definition.endpoints.create) {
                await ResourceService.create(definition.endpoints.create, form);
            }
            navigate('/admin/projects');
        } catch (err: any) {
            setError(err?.message || 'Failed to save project.');
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async (projectId: string | number) => {
        if (!definition.endpoints.delete) return;
        if (!window.confirm(`Are you sure you want to delete project #${projectId}?`)) return;
        try {
            await ResourceService.delete(definition.endpoints.delete(projectId));
            loadList(page);
        } catch (err: any) {
            setError(err?.message || 'Failed to delete project.');
        }
    };

    const handleSyncMissingLog = async () => {
        setSyncing(true);
        setNotice('');
        setError('');
        try {
            const res = await apiClient<any>('/api/admin/projects/sync-missing-log', { method: 'POST' });
            setNotice(`Sync complete: Checked ${res?.data?.checked ?? 0} projects, created ${res?.data?.created ?? 0} missing log tables.`);
        } catch (err: any) {
            setError(err?.message || 'Failed to sync log tables.');
        } finally {
            setSyncing(false);
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
        syncing,
        error,
        notice,
        searchTerm,
        setSearchTerm,
        selectedSlug,
        setSelectedSlug,
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
        handleSyncMissingLog,
    };
}
