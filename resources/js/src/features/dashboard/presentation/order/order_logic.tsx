import { useEffect, useState } from 'react';
import { orderService } from '../../application/order_service';
import { paymentRepositoryService } from '../../application/payment_repository_service';
import { OrderItem } from '../../domain/model/response/order/order_response';

export interface UseOrderLogicProps {
    mode: 'resource-index' | 'resource-create' | 'resource-edit' | 'resource-show';
    id?: string | number;
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

    // Filters
    const [searchTerm, setSearchTerm] = useState<string>('');
    const [page, setPage] = useState<number>(1);
    const [perPage, setPerPage] = useState<number>(10);
    const [selectedMode, setSelectedMode] = useState<string>('all');
    const [selectedStatus, setSelectedStatus] = useState<string>('all');
    const [startDate, setStartDate] = useState<string>('');
    const [endDate, setEndDate] = useState<string>('');
    const [selectedRepository, setSelectedRepository] = useState<string>('all');
    const [repositories, setRepositories] = useState<Array<{ id: string | number; label: string }>>([]);

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

    const loadList = async (pageNum = page) => {
        setLoading(true);
        setError('');
        try {
            const res = await orderService.getOrders({
                page: pageNum,
                per_page: perPage,
                search: searchTerm.trim() || undefined,
                mode: selectedMode !== 'all' ? selectedMode : undefined,
                status: selectedStatus !== 'all' ? selectedStatus : undefined,
                start_date: startDate || undefined,
                end_date: endDate || undefined,
                payment_repository_id: selectedRepository !== 'all' ? selectedRepository : undefined,
            });
            setPayload(res);
            setPage(pageNum);
        } catch (err: any) {
            setError(err?.message || 'Failed to load orders.');
        } finally {
            setLoading(false);
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
        } else if (mode === 'resource-show') {
            loadItem();
        }
    }, [mode, id, selectedMode, selectedStatus, startDate, endDate, selectedRepository, perPage]);

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        loadList(1);
    };

    const handleClearSearch = () => {
        setSearchTerm('');
        setStartDate('');
        setEndDate('');
        setSelectedRepository('all');
        setSelectedMode('all');
        setSelectedStatus('all');
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
        searchTerm,
        setSearchTerm,
        page,
        perPage,
        setPerPage,
        total,
        currentPage,
        currentPerPage,
        selectedMode,
        setSelectedMode,
        selectedStatus,
        setSelectedStatus,
        startDate,
        setStartDate,
        endDate,
        setEndDate,
        selectedRepository,
        setSelectedRepository,
        repositories,
        loadList,
        loadItem,
        handleSearchSubmit,
        handleClearSearch,
        handleResendCallback,
        handleSetSuccess,
    };
}
