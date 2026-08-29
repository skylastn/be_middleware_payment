import { useEffect, useState } from 'react';
import { projectService } from '../../application/project_service';
import { ProjectItem } from '../../domain/model/project/project_model';
import { navigate } from '@/shared/utils/format_utils';

export interface UseProjectLogicProps {
    mode: 'resource-index' | 'resource-create' | 'resource-edit' | 'resource-show';
    id?: string | number;
}

export function useProjectLogic({ mode, id }: UseProjectLogicProps) {
    const isEdit = mode === 'resource-edit';

    const [payload, setPayload] = useState<any>(null);
    const [record, setRecord] = useState<ProjectItem | null>(null);
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
            const res = await projectService.getProjects({
                page: pageNum,
                per_page: perPage,
                search: searchTerm.trim() || undefined,
                slug: selectedSlug !== 'all' ? selectedSlug : undefined,
            });
            setPayload(res);
            setPage(pageNum);
        } catch (err: any) {
            setError(err?.message || 'Failed to load projects.');
        } finally {
            setLoading(false);
        }
    };

    const loadItem = async () => {
        if (!id) return;
        setLoading(true);
        setError('');
        try {
            const itemData = await projectService.getProjectById(id);
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
            if (isEdit && id) {
                await projectService.updateProject(id, form);
            } else {
                await projectService.createProject(form);
            }
            navigate('/admin/projects');
        } catch (err: any) {
            setError(err?.message || 'Failed to save project.');
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async (projectId: string | number) => {
        if (!window.confirm(`Are you sure you want to delete project #${projectId}?`)) return;
        try {
            await projectService.deleteProject(projectId);
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
            const res = await projectService.syncMissingLog();
            setNotice(`Sync complete: Checked ${res?.data?.checked ?? 0} projects, created ${res?.data?.created ?? 0} missing log tables.`);
        } catch (err: any) {
            setError(err?.message || 'Failed to sync log tables.');
        } finally {
            setSyncing(false);
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
