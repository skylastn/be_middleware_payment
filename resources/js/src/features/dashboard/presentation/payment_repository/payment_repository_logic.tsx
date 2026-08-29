import { useEffect, useState } from 'react';
import { paymentRepositoryService } from '../../application/payment_repository_service';
import { paymentGatewayService } from '../../application/payment_gateway_service';
import { PaymentRepositoryItem } from '../../domain/model/response/payment/payment_repository_response';
import { navigate } from '@/shared/utils/format_utils';

export interface UsePaymentRepositoryLogicProps {
    mode: 'resource-index' | 'resource-create' | 'resource-edit' | 'resource-show';
    id?: string | number;
}

export function usePaymentRepositoryLogic({ mode, id }: UsePaymentRepositoryLogicProps) {
    const isEdit = mode === 'resource-edit';

    const [payload, setPayload] = useState<any>(null);
    const [record, setRecord] = useState<PaymentRepositoryItem | null>(null);
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
    const [notice, setNotice] = useState<string>('');
    const [gateways, setGateways] = useState<Array<{ id: string | number; name: string; key: string }>>([]);

    // Test Order State
    const [testModalRepo, setTestModalRepo] = useState<any | null>(null);
    const [testingOrder, setTestingOrder] = useState<boolean>(false);
    const [testOrderResult, setTestOrderResult] = useState<any | null>(null);
    const [testError, setTestError] = useState<string>('');
    const [testForm, setTestForm] = useState({
        amount: 10000,
        currency: 'IDR',
        email: 'test-customer@example.com',
        name: 'Test Buyer',
        paymentMethod: '',
        version: '1',
    });

    // Filter states
    const [searchTerm, setSearchTerm] = useState<string>('');
    const [selectedMode, setSelectedMode] = useState<string>('all');
    const [page, setPage] = useState<number>(1);
    const [perPage, setPerPage] = useState<number>(10);

    useEffect(() => {
        paymentGatewayService.getPaymentGateways({ per_page: 100 })
            .then((res: any) => {
                const items = res?.data || [];
                setGateways(items.map((g: any) => ({ id: g.id, name: g.name || g.key, key: g.key })));
            })
            .catch(() => {});
    }, []);

    const loadList = async (pageNum = page) => {
        setLoading(true);
        setError('');
        try {
            const res = await paymentRepositoryService.getPaymentRepositories({
                page: pageNum,
                per_page: perPage,
                search: searchTerm.trim() || undefined,
                mode: selectedMode !== 'all' ? selectedMode : undefined,
            });
            setPayload(res);
            setPage(pageNum);
        } catch (err: any) {
            setError(err?.message || 'Failed to load payment repositories.');
        } finally {
            setLoading(false);
        }
    };

    const loadItem = async () => {
        if (!id) return;
        setLoading(true);
        setError('');
        try {
            const itemData = await paymentRepositoryService.getPaymentRepositoryById(id);
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
            if (isEdit && id) {
                await paymentRepositoryService.updatePaymentRepository(id, submitData);
            } else {
                await paymentRepositoryService.createPaymentRepository(submitData);
            }
            navigate('/admin/payment-repositories');
        } catch (err: any) {
            setError(err?.message || 'Failed to save payment repository.');
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async (repoId: string | number) => {
        if (!window.confirm(`Are you sure you want to delete payment repository #${repoId}?`)) return;
        try {
            await paymentRepositoryService.deletePaymentRepository(repoId);
            loadList(page);
        } catch (err: any) {
            setError(err?.message || 'Failed to delete payment repository.');
        }
    };

    const openTestModal = (repo: any) => {
        const gwKey = repo.payment_gateway?.key || repo.key || '';
        const isStripe = String(gwKey).toLowerCase().includes('stripe');
        setTestModalRepo(repo);
        setTestOrderResult(null);
        setTestError('');
        setTestForm({
            amount: isStripe ? 50 : 10000,
            currency: isStripe ? 'MYR' : 'IDR',
            email: 'test-buyer@example.com',
            name: 'Test Buyer',
            paymentMethod: '',
            version: '1',
        });
    };

    const closeTestModal = () => {
        setTestModalRepo(null);
        setTestOrderResult(null);
        setTestError('');
    };

    const handleExecuteTestOrder = async (e?: React.FormEvent) => {
        if (e) e.preventDefault();
        if (!testModalRepo) return;
        setTestingOrder(true);
        setTestError('');
        try {
            const res = await paymentRepositoryService.testOrder(testModalRepo.id, testForm);
            setTestOrderResult(res?.data || res);
        } catch (err: any) {
            setTestError(err?.message || 'Failed to execute test order on gateway.');
        } finally {
            setTestingOrder(false);
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
        gateways,
        jsonError,
        loading,
        saving,
        error,
        notice,
        testModalRepo,
        testingOrder,
        testOrderResult,
        testError,
        testForm,
        setTestForm,
        openTestModal,
        closeTestModal,
        handleExecuteTestOrder,
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
