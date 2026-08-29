import { useEffect, useState } from 'react';
import { settingService } from '../../application/setting_service';
import { SettingItem } from '../../domain/model/setting/setting_model';
import { navigate } from '@/shared/utils/format_utils';

export interface UseSettingLogicProps {
    mode: 'resource-index' | 'resource-create' | 'resource-edit' | 'resource-show';
    id?: string | number;
}

export function useSettingLogic({ mode, id }: UseSettingLogicProps) {
    const isEdit = mode === 'resource-edit';

    const [payload, setPayload] = useState<any>(null);
    const [record, setRecord] = useState<SettingItem | null>(null);
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
            const res = await settingService.getSettings({
                page: pageNum,
                per_page: perPage,
                search: searchTerm.trim() || undefined,
            });
            setPayload(res);
            setPage(pageNum);
        } catch (err: any) {
            setError(err?.message || 'Failed to load settings.');
        } finally {
            setLoading(false);
        }
    };

    const loadItem = async () => {
        if (!id) return;
        setLoading(true);
        setError('');
        try {
            const itemData = await settingService.getSettingById(id);
            setRecord(itemData);
            if (isEdit) {
                setForm({
                    key: itemData.key || '',
                    value: itemData.value || '',
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
            if (isEdit && id) {
                await settingService.updateSetting(id, form);
            } else {
                await settingService.createSetting(form);
            }
            navigate('/admin/settings');
        } catch (err: any) {
            setError(err?.message || 'Failed to save setting.');
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async (settingId: string | number) => {
        if (!window.confirm(`Are you sure you want to delete setting #${settingId}?`)) return;
        try {
            await settingService.deleteSetting(settingId);
            loadList(page);
        } catch (err: any) {
            setError(err?.message || 'Failed to delete setting.');
        }
    };

    const records = payload?.data || [];
    const total = Number(payload?.total ?? records.length);
    const currentPage = Number(payload?.currentPage ?? page);
    const currentPerPage = Number(payload?.perPage ?? perPage);

    return {
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
