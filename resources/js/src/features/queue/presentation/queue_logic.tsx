import { useEffect, useState } from 'react';
import {
    FailedJobItem,
    QueueJobItem,
    QueueOverview,
} from '../domain/model/queue_model';
import { queueService } from '../application/queue_service';

export function useQueueLogic() {
    const [overview, setOverview] = useState<QueueOverview | null>(null);
    const [activeJobs, setActiveJobs] = useState<QueueJobItem[]>([]);
    const [failedJobs, setFailedJobs] = useState<FailedJobItem[]>([]);
    const [tab, setTab] = useState<'active' | 'failed' | 'info'>('active');
    const [activeTypeFilter, setActiveTypeFilter] = useState<'all' | 'pending' | 'scheduled' | 'reserved'>('all');
    const [failedSearch, setFailedSearch] = useState('');
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [autoRefresh, setAutoRefresh] = useState(false);
    const [selectedJob, setSelectedJob] = useState<QueueJobItem | FailedJobItem | null>(null);

    // Test job dispatch state
    const [dispatchModalOpen, setDispatchModalOpen] = useState(false);
    const [testMessage, setTestMessage] = useState('Manual test queue job');
    const [testDelay, setTestDelay] = useState(0);
    const [dispatching, setDispatching] = useState(false);
    const [actionMessage, setActionMessage] = useState<string | null>(null);

    const loadOverview = async () => {
        try {
            const res = await queueService.getOverview();
            if (res.status && res.data) {
                setOverview(res.data);
            }
        } catch (e) {
            console.error('Failed to load queue overview', e);
        }
    };

    const loadActiveJobs = async () => {
        try {
            const res = await queueService.getActiveJobs(activeTypeFilter);
            if (res.status && res.data) {
                setActiveJobs(res.data);
            }
        } catch (e) {
            console.error('Failed to load active jobs', e);
        }
    };

    const loadFailedJobs = async () => {
        try {
            const res = await queueService.getFailedJobs(1, 20, failedSearch);
            if (res.status && res.data) {
                setFailedJobs(res.data);
            }
        } catch (e) {
            console.error('Failed to load failed jobs', e);
        }
    };

    const refreshAll = async () => {
        setRefreshing(true);
        await Promise.all([loadOverview(), loadActiveJobs(), loadFailedJobs()]);
        setRefreshing(false);
        setLoading(false);
    };

    useEffect(() => {
        refreshAll();
    }, []);

    useEffect(() => {
        if (tab === 'active') {
            loadActiveJobs();
        } else if (tab === 'failed') {
            loadFailedJobs();
        }
    }, [tab, activeTypeFilter, failedSearch]);

    // Auto-refresh timer
    useEffect(() => {
        if (!autoRefresh) return;
        const timer = setInterval(() => {
            void refreshAll();
        }, 5000);

        return () => clearInterval(timer);
    }, [autoRefresh, tab, activeTypeFilter]);

    const handleRetryJob = async (id: string | number) => {
        try {
            const res = await queueService.retryFailedJob(id);
            setActionMessage(`Job #${id} retry queued.`);
            await refreshAll();
        } catch (e: any) {
            alert('Failed to retry job: ' + e.message);
        }
    };

    const handleRetryAll = async () => {
        if (!confirm('Are you sure you want to retry all failed jobs?')) return;
        try {
            await queueService.retryAllFailedJobs();
            setActionMessage('All failed jobs have been queued for retry.');
            await refreshAll();
        } catch (e: any) {
            alert('Failed to retry all jobs: ' + e.message);
        }
    };

    const handleForgetJob = async (id: string | number) => {
        if (!confirm(`Delete failed job #${id}?`)) return;
        try {
            await queueService.forgetFailedJob(id);
            setActionMessage(`Job #${id} deleted.`);
            await refreshAll();
        } catch (e: any) {
            alert('Failed to delete job: ' + e.message);
        }
    };

    const handleFlushFailed = async () => {
        if (!confirm('Are you sure you want to delete ALL failed jobs? This cannot be undone.')) return;
        try {
            await queueService.flushFailedJobs();
            setActionMessage('All failed jobs deleted.');
            await refreshAll();
        } catch (e: any) {
            alert('Failed to flush jobs: ' + e.message);
        }
    };

    const handleDispatchTest = async () => {
        setDispatching(true);
        try {
            await queueService.dispatchTestJob(testMessage, testDelay);
            setDispatchModalOpen(false);
            setActionMessage(`Test job dispatched (${testDelay > 0 ? `${testDelay}s delay` : 'immediate'}).`);
            await refreshAll();
        } catch (e: any) {
            alert('Failed to dispatch test job: ' + e.message);
        } finally {
            setDispatching(false);
        }
    };

    return {
        overview,
        activeJobs,
        failedJobs,
        tab,
        setTab,
        activeTypeFilter,
        setActiveTypeFilter,
        failedSearch,
        setFailedSearch,
        loading,
        refreshing,
        autoRefresh,
        setAutoRefresh,
        selectedJob,
        setSelectedJob,
        dispatchModalOpen,
        setDispatchModalOpen,
        testMessage,
        setTestMessage,
        testDelay,
        setTestDelay,
        dispatching,
        actionMessage,
        setActionMessage,
        refreshAll,
        handleRetryJob,
        handleRetryAll,
        handleForgetJob,
        handleFlushFailed,
        handleDispatchTest,
    };
}
