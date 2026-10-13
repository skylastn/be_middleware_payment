import { useEffect, useState } from 'react';
import { orderService } from '../../application/order_service';
import { paymentRepositoryService } from '../../application/payment_repository_service';
import { OrderItem } from '../../domain/model/response/order/order_response';
import { OrderSummaryTotals } from '../../domain/model/response/order/order_summary_response';

export interface UseOrderLogicProps {
    mode: 'resource-index' | 'resource-create' | 'resource-edit' | 'resource-show';
    id?: string | number;
}

export interface OrderFilterState {
    searchTerm: string;
    mode: string;
    status: string;
    startDate: string;
    endDate: string;
    repository: string;
    email: string;
    hourFrom: string;
    hourTo: string;
}

const EMPTY_FILTERS: OrderFilterState = {
    searchTerm: '',
    mode: 'all',
    status: 'all',
    startDate: '',
    endDate: '',
    repository: 'all',
    email: '',
    hourFrom: '',
    hourTo: '',
};

function countActiveFilters(state: OrderFilterState): number {
    let count = 0;
    if (state.searchTerm.trim()) count++;
    if (state.mode !== 'all') count++;
    if (state.status !== 'all') count++;
    if (state.startDate) count++;
    if (state.endDate) count++;
    if (state.repository !== 'all') count++;
    if (state.email.trim()) count++;
    if (state.hourFrom.trim() || state.hourTo.trim()) count++;
    return count;
}

export function useOrderLogic({ mode, id }: UseOrderLogicProps) {
    const [payload, setPayload] = useState<any>(null);
    const [record, setRecord] = useState<OrderItem | null>(null);
    const [loading, setLoading] = useState<boolean>(true);
    const [error, setError] = useState<string>('');
    const [notice, setNotice] = useState<string>('');
    const [resending, setResending] = useState<boolean>(false);
    const [updatingStatus, setUpdatingStatus] = useState<boolean>(false);
    const [confirmSuccessOrder, setConfirmSuccessOrder] = useState<{ id: string | number; reference?: string } | null>(null);

    // Applied filters — these drive the results shown on the page.
    const [searchTerm, setSearchTerm] = useState<string>('');
    const [page, setPage] = useState<number>(1);
    const [perPage, setPerPage] = useState<number>(10);
    const [selectedRepository, setSelectedRepository] = useState<string>('all');
    const [repositories, setRepositories] = useState<Array<{ id: string | number; label: string }>>([]);
    const [summary, setSummary] = useState<OrderSummaryTotals | null>(null);
    const [summaryLoading, setSummaryLoading] = useState<boolean>(false);

    // Draft filters — edited inside the dialog, only applied on submit.
    const [filterDialogOpen, setFilterDialogOpen] = useState<boolean>(false);
    const [applied, setApplied] = useState<OrderFilterState>(EMPTY_FILTERS);
    const [draft, setDraft] = useState<OrderFilterState>(EMPTY_FILTERS);

    useEffect(() => {
        if (mode === 'resource-index') {
            paymentRepositoryService.getPaymentRepositories({ per_page: 100 })
                .then((res: any) => {
                    const items = res?.data || [];
                    setRepositories(
                        items.map((repo: any) => ({
                            id: repo.id,
                            label: `${repo.payment_gateway?.name || repo.key || 'Repository'} (${repo.mode || 'sandbox'}) - #${repo.id}`,
                        }))
                    );
                })
                .catch(() => {});
        }
    }, [mode]);

    const buildFilters = (source: OrderFilterState = applied) => {
        const from = source.hourFrom.trim();
        const to = source.hourTo.trim();
        return {
            search: source.searchTerm.trim() || undefined,
            mode: source.mode !== 'all' ? source.mode : undefined,
            status: source.status !== 'all' ? source.status : undefined,
            start_date: source.startDate || undefined,
            end_date: source.endDate || undefined,
            payment_repository_id: source.repository !== 'all' ? source.repository : undefined,
            email: source.email.trim() || undefined,
            start_time: from || undefined,
            end_time: to || undefined,
        };
    };

    const activeFilterCount = countActiveFilters(applied);

    const openFilterDialog = () => {
        setDraft(applied);
        setFilterDialogOpen(true);
    };

    const closeFilterDialog = () => {
        setDraft(applied);
        setFilterDialogOpen(false);
    };

    const applyFilters = () => {
        setApplied(draft);
        setFilterDialogOpen(false);
        setPage(1);
        loadList(1, draft);
        loadSummary(draft);
    };

    const resetFilters = () => {
        setDraft(EMPTY_FILTERS);
        setApplied(EMPTY_FILTERS);
        setFilterDialogOpen(false);
        setPage(1);
        loadList(1, EMPTY_FILTERS);
        loadSummary(EMPTY_FILTERS);
    };

    // Quick removal of a single active filter straight from the chip row.
    const removeFilter = (patch: Partial<OrderFilterState>) => {
        const next = { ...applied, ...patch };
        setApplied(next);
        setDraft(next);
        setPage(1);
        loadList(1, next);
        loadSummary(next);
    };

    // Quick chips (Today / 7 days / ...) live outside the dialog and re-apply immediately.
    const applyDatePreset = (start: string, end: string) => {
        const next = { ...applied, startDate: start, endDate: end };
        setApplied(next);
        setDraft(next);
        setPage(1);
        loadList(1, next);
        loadSummary(next);
    };

    const loadList = async (pageNum = page, source: OrderFilterState = applied, size: number = perPage) => {
        setLoading(true);
        setError('');
        try {
            const res = await orderService.getOrders({
                ...buildFilters(source),
                page: pageNum,
                per_page: size,
            });
            setPayload(res);
            setPage(pageNum);
        } catch (err: any) {
            setError(err?.message || 'Failed to load orders.');
        } finally {
            setLoading(false);
        }
    };

    const loadSummary = async (source: OrderFilterState = applied) => {
        setSummaryLoading(true);
        try {
            const res = await orderService.getOrderSummary(buildFilters(source));
            setSummary(res);
        } catch {
            setSummary(null);
        } finally {
            setSummaryLoading(false);
        }
    };

    const loadItem = async () => {
        if (!id) return;
        setLoading(true);
        setError('');
        try {
            const res = await orderService.getOrderById(id);
            setRecord(res);
        } catch (err: any) {
            setError(err?.message || 'Failed to load order details.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (mode === 'resource-index') {
            loadList(1);
            loadSummary();
        } else if (mode === 'resource-show') {
            loadItem();
        }
    }, [mode, id, perPage]);

    const handlePerPageChange = (nextSize: number) => {
        setPerPage(nextSize);
        loadList(1, applied, nextSize);
    };

    const handleResendCallback = async (orderId: string | number) => {
        setResending(true);
        setNotice('');
        setError('');
        try {
            await orderService.resendCallback(orderId);
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

    const handleSetSuccess = (orderId: string | number, reference?: string) => {
        setConfirmSuccessOrder({ id: orderId, reference });
    };

    const confirmSetSuccessAction = async () => {
        if (!confirmSuccessOrder) return;
        const orderId = confirmSuccessOrder.id;
        setUpdatingStatus(true);
        setNotice('');
        setError('');
        try {
            await orderService.setSuccess(orderId);
            setNotice(`Order #${orderId} marked as SUCCESS and callback sent.`);
            setConfirmSuccessOrder(null);
            if (mode === 'resource-show') {
                loadItem();
            } else {
                loadList(page);
                loadSummary();
            }
        } catch (err: any) {
            setError(err?.message || 'Failed to update order status.');
        } finally {
            setUpdatingStatus(false);
        }
    };

    const records = payload?.data || [];
    const total = Number(payload?.total ?? records.length);
    const currentPage = Number(payload?.currentPage ?? page);
    const currentPerPage = Number(payload?.perPage ?? perPage);

    return {
        payload,
        records,
        record,
        loading,
        error,
        notice,
        resending,
        updatingStatus,
        confirmSuccessOrder,
        setConfirmSuccessOrder,
        confirmSetSuccessAction,
        page,
        perPage,
        total,
        currentPage,
        currentPerPage,
        repositories,
        applied,
        draft,
        setDraft,
        activeFilterCount,
        filterDialogOpen,
        openFilterDialog,
        closeFilterDialog,
        applyFilters,
        resetFilters,
        removeFilter,
        applyDatePreset,
        summary,
        summaryLoading,
        loadSummary,
        loadList,
        loadItem,
        handlePerPageChange,
        handleResendCallback,
        handleSetSuccess,
    };
}
