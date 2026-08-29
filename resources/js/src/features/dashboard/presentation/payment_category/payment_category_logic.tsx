import { useEffect, useState } from 'react';
import { paymentCategoryService } from '../../application/payment_category_service';
import { PaymentCategoryItem } from '../../domain/model/response/payment/payment_category_response';
import { navigate } from '@/shared/utils/format_utils';

export interface UsePaymentCategoryLogicProps {
    mode: 'resource-index' | 'resource-create' | 'resource-edit' | 'resource-show';
    id?: string | number;
}

export function usePaymentCategoryLogic({ mode, id }: UsePaymentCategoryLogicProps) {
    const isEdit = mode === 'resource-edit';

    const [payload, setPayload] = useState<any>(null);
    const [record, setRecord] = useState<PaymentCategoryItem | null>(null);
    const [form, setForm] = useState<Record<string, any>>({
        key: '',
        title: '',
        detail: '',
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
            const res = await paymentCategoryService.getPaymentCategories({
                page: pageNum,
                per_page: perPage,
                search: searchTerm.trim() || undefined,
            });
            setPayload(res);
            setPage(pageNum);
        } catch (err: any) {
            setError(err?.message || 'Failed to load payment categories.');
        } finally {
            setLoading(false);
        }
    };

    const loadItem = async () => {
        if (!id) return;
        setLoading(true);
        setError('');
        try {
            const itemData = await paymentCategoryService.getPaymentCategoryById(id);
            setRecord(itemData);
            if (isEdit) {
                setForm({
                    key: itemData.key || '',
                    title: itemData.title || '',
                    detail: itemData.detail || '',
                });
            }
        } catch (err: any) {
            setError(err?.message || 'Failed to load category details.');
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
                await paymentCategoryService.updatePaymentCategory(id, form);
            } else {
                await paymentCategoryService.createPaymentCategory(form);
            }
            navigate('/admin/payment-categories');
        } catch (err: any) {
            setError(err?.message || 'Failed to save payment category.');
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async (categoryId: string | number) => {
        if (!window.confirm(`Are you sure you want to delete payment category #${categoryId}?`)) return;
        try {
            await paymentCategoryService.deletePaymentCategory(categoryId);
            loadList(page);
        } catch (err: any) {
            setError(err?.message || 'Failed to delete payment category.');
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
