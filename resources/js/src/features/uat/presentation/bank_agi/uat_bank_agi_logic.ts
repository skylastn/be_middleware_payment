import { useCallback, useEffect, useState } from 'react';
import { uatService } from '../../application/uat_service';
import { UatOrderItem } from '../../domain/model/response/uat_order_response';
import { UatRepositoryItem } from '../../domain/model/response/uat_repository_response';

export interface NotifyResult {
    notify_response: any;
    local_status: string | null;
}

export function useUatTestLogic(gateway: string) {
    const [repositories, setRepositories] = useState<UatRepositoryItem[]>([]);
    const [selectedRepoId, setSelectedRepoId] = useState<string>('');
    const [modeFilter, setModeFilter] = useState<string>('sandbox');
    const [loadingRepos, setLoadingRepos] = useState<boolean>(false);
    const [error, setError] = useState<string>('');
    const [notice, setNotice] = useState<string>('');

    const [amount, setAmount] = useState<number>(10000);
    const [expiryPeriod, setExpiryPeriod] = useState<number>(60);
    const [qrResult, setQrResult] = useState<any | null>(null);
    const [generating, setGenerating] = useState<boolean>(false);

    const [queryResult, setQueryResult] = useState<any | null>(null);
    const [querying, setQuerying] = useState<boolean>(false);

    const [notifyStatus, setNotifyStatus] = useState<string>('00');
    const [notifyAmount, setNotifyAmount] = useState<string>('');
    const [notifyResult, setNotifyResult] = useState<NotifyResult | null>(null);
    const [notifying, setNotifying] = useState<boolean>(false);

    const [orders, setOrders] = useState<UatOrderItem[]>([]);
    const [loadingOrders, setLoadingOrders] = useState<boolean>(false);

    const [negativeResult, setNegativeResult] = useState<any | null>(null);
    const [negativeCase, setNegativeCase] = useState<string>('');
    const [runningNegative, setRunningNegative] = useState<string>('');

    const loadRepositories = useCallback(async (mode: string) => {
        setLoadingRepos(true);
        setError('');
        try {
            const rows = await uatService.getRepositories(gateway, mode);
            setRepositories(rows);
            setSelectedRepoId((prev) => {
                if (prev && rows.some((r: UatRepositoryItem) => r.id === prev)) return prev;
                return rows[0]?.id || '';
            });
        } catch (err: any) {
            setError(err?.message || 'Failed to load repositories.');
        } finally {
            setLoadingRepos(false);
        }
    }, [gateway]);

    const loadOrders = useCallback(async () => {
        setLoadingOrders(true);
        try {
            setOrders(await uatService.listOrders(20));
        } catch {
            setOrders([]);
        } finally {
            setLoadingOrders(false);
        }
    }, []);

    useEffect(() => {
        loadRepositories(modeFilter);
        loadOrders();
    }, [modeFilter, loadRepositories, loadOrders]);

    const selectedRepo = repositories.find((r) => r.id === selectedRepoId) || null;

    const handleGenerateQr = async (override?: { amount?: number; expiryPeriod?: number }) => {
        if (!selectedRepoId) {
            setError('Select a repository first.');
            return;
        }
        const useAmount = override?.amount ?? amount;
        const useExpiry = override?.expiryPeriod ?? expiryPeriod;
        setGenerating(true);
        setError('');
        setNotice('');
        try {
            setQrResult(await uatService.generateQr({
                repositoryId: selectedRepoId,
                amount: useAmount,
                expiryPeriod: useExpiry,
            }));
            setNotice('QR generated successfully.');
            loadOrders();
        } catch (err: any) {
            setError(err?.message || 'Failed to generate QR.');
        } finally {
            setGenerating(false);
        }
    };

    const handleQueryStatus = async (reference?: string) => {
        const ref = reference || qrResult?.reference;
        if (!ref) {
            setError('No reference to query. Generate a QR first or enter a reference.');
            return;
        }
        setQuerying(true);
        setError('');
        try {
            setQueryResult(await uatService.queryStatus({ reference: ref }));
        } catch (err: any) {
            setError(err?.message || 'Failed to query status.');
        } finally {
            setQuerying(false);
        }
    };

    const handleNotify = async (override?: { latestTransactionStatus?: string }) => {
        const ref = qrResult?.reference;
        if (!ref) {
            setError('Generate a QR first before sending a notify.');
            return;
        }
        setNotifying(true);
        setError('');
        try {
            setNotifyResult(await uatService.notify({
                reference: ref,
                latestTransactionStatus: override?.latestTransactionStatus ?? notifyStatus,
                amount: notifyAmount ? Number(notifyAmount) : undefined,
            }));
            loadOrders();
        } catch (err: any) {
            setError(err?.message || 'Failed to send notify.');
        } finally {
            setNotifying(false);
        }
    };

    const handleBankProbe = async (testCase: string) => {
        if (!selectedRepoId) {
            setError('Select a repository first.');
            return;
        }
        setRunningNegative(testCase);
        setNegativeCase(testCase);
        setNegativeResult(null);
        setError('');
        try {
            setNegativeResult(await uatService.probeBank({ repositoryId: selectedRepoId, case: testCase }));
        } catch (err: any) {
            setError(err?.message || 'Bank probe failed.');
        } finally {
            setRunningNegative('');
        }
    };

    return {
        negativeResult,
        negativeCase,
        runningNegative,
        handleBankProbe,
        repositories,
        selectedRepoId,
        setSelectedRepoId,
        selectedRepo,
        modeFilter,
        setModeFilter,
        loadingRepos,
        error,
        setError,
        notice,
        amount,
        setAmount,
        expiryPeriod,
        setExpiryPeriod,
        qrResult,
        setQrResult,
        generating,
        handleGenerateQr,
        queryResult,
        querying,
        handleQueryStatus,
        notifyStatus,
        setNotifyStatus,
        notifyAmount,
        setNotifyAmount,
        notifyResult,
        notifying,
        handleNotify,
        orders,
        loadingOrders,
        loadOrders,
        loadRepositories,
    };
}
