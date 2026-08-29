import { useEffect, useState } from 'react';
import { paymentMethodService } from '../../application/payment_method_service';
import { PaymentMethodItem } from '../../domain/model/payment/payment_method_model';
import { navigate } from '@/shared/utils/format_utils';

export interface UsePaymentMethodLogicProps {
    mode: 'resource-index' | 'resource-create' | 'resource-edit' | 'resource-show';
    id?: string | number;
}

export function usePaymentMethodLogic({ mode, id }: UsePaymentMethodLogicProps) {
    const isEdit = mode === 'resource-edit';

    const [payload, setPayload] = useState<any>(null);
    const [record, setRecord] = useState<PaymentMethodItem | null>(null);
    const [form, setForm] = useState<Record<string, any>>({
        key: '',
        name: '',
        type: 'bank_transfer',
        from: 'duitku',
        bankCode: '',
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
            const res = await paymentMethodService.getPaymentMethods({
                page: pageNum,
                per_page: perPage,
                search: searchTerm.trim() || undefined,
            });
            setPayload(res);
            setPage(pageNum);
        } catch (err: any) {
            setError(err?.message || 'Failed to load payment methods.');
        } finally {
            setLoading(false);
        }
    };

    const loadItem = async () => {
        if (!id) return;
        setLoading(true);
        setError('');
        try {
            const itemData = await paymentMethodService.getPaymentMethodById(id);
            setRecord(itemData);
            if (isEdit) {
                setForm({
                    key: itemData.key || '',
                    name: itemData.name || '',
                    type: itemData.type || 'bank_transfer',
                    from: itemData.from || 'duitku',
                    bankCode: itemData.bankCode || '',
                    value: itemData.value || '',
                });
            }
        } catch (err: any) {
            setError(err?.message || 'Failed to load method details.');
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
                await paymentMethodService.updatePaymentMethod(id, form);
            } else {
                await paymentMethodService.createPaymentMethod(form);
            }
            navigate('/admin/payment-methods');
        } catch (err: any) {
            setError(err?.message || 'Failed to save payment method.');
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async (methodId: string | number) => {
        if (!window.confirm(`Are you sure you want to delete payment method #${methodId}?`)) return;
        try {
            await paymentMethodService.deletePaymentMethod(methodId);
            loadList(page);
        } catch (err: any) {
            setError(err?.message || 'Failed to delete payment method.');
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
