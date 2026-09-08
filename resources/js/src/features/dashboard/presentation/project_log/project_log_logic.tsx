import { useEffect, useRef, useState } from 'react';
import { projectService } from '../../application/project_service';
import { ProjectItem } from '../../domain/model/response/project/project_response';
import { ProjectLogItem } from '../../domain/model/response/project/project_log_response';

export interface UseProjectLogLogicProps {
    projectId: string | number;
}

export function useProjectLogLogic({ projectId }: UseProjectLogLogicProps) {
    const [project, setProject] = useState<ProjectItem | null>(null);
    const [logs, setLogs] = useState<ProjectLogItem[]>([]);
    const [availableKeys, setAvailableKeys] = useState<string[]>([]);
    const [loading, setLoading] = useState<boolean>(true);
    const [error, setError] = useState<string>('');
    const [clearing, setClearing] = useState<boolean>(false);
    const [isClearModalOpen, setIsClearModalOpen] = useState<boolean>(false);

    // Filters
    const [searchTerm, setSearchTerm] = useState<string>('');
    const [selectedKey, setSelectedKey] = useState<string>('all');
    const [dateFrom, setDateFrom] = useState<string>('');
    const [dateTo, setDateTo] = useState<string>('');
    const [page, setPage] = useState<number>(1);
    const [perPage, setPerPage] = useState<number>(20);
    const [total, setTotal] = useState<number>(0);

    // Auto-refresh interval (0 = off, 5 = 5s, 10 = 10s, 30 = 30s)
    const [autoRefreshInterval, setAutoRefreshInterval] = useState<number>(0);

    // Interactive inspector modal & row accordion
    const [selectedLogForModal, setSelectedLogForModal] = useState<ProjectLogItem | null>(null);
    const [expandedRowIds, setExpandedRowIds] = useState<Set<string | number>>(new Set());

    const timerRef = useRef<any>(null);

    const loadProjectDetails = async () => {
        try {
            const res = await projectService.getProjectById(projectId);
            if (res) setProject(res);
        } catch {
            // Ignore project fetch errors silently
        }
    };

    const loadLogKeys = async () => {
        try {
            const keys = await projectService.getProjectLogKeys(projectId);
            if (Array.isArray(keys)) setAvailableKeys(keys);
        } catch {
            // Ignore key fetch errors
        }
    };

    const loadLogs = async (pageNum = page, isBackground = false) => {
        if (!isBackground) {
            setLoading(true);
        }
        setError('');
        try {
            const res = await projectService.getProjectLogs(projectId, {
                page: pageNum,
                per_page: perPage,
                search: searchTerm.trim() || undefined,
                key: selectedKey !== 'all' ? selectedKey : undefined,
                date_from: dateFrom || undefined,
                date_to: dateTo || undefined,
            });

            if (res) {
                setLogs(res.data || []);
                setTotal(res.total ?? 0);
                setPage(res.currentPage ?? pageNum);
            }
        } catch (err: any) {
            if (!isBackground) {
                setError(err?.message || 'Failed to load project logs.');
            }
        } finally {
            if (!isBackground) {
                setLoading(false);
            }
        }
    };

    useEffect(() => {
        loadProjectDetails();
        loadLogKeys();
    }, [projectId]);

    useEffect(() => {
        loadLogs(1);
    }, [projectId, selectedKey, perPage, dateFrom, dateTo]);

    // Auto-refresh hook
    useEffect(() => {
        if (timerRef.current) {
            clearInterval(timerRef.current);
            timerRef.current = null;
        }

        if (autoRefreshInterval > 0) {
            timerRef.current = setInterval(() => {
                loadLogs(page, true);
            }, autoRefreshInterval * 1000);
        }

        return () => {
            if (timerRef.current) {
                clearInterval(timerRef.current);
                timerRef.current = null;
            }
        };
    }, [autoRefreshInterval, projectId, page, perPage, searchTerm, selectedKey, dateFrom, dateTo]);

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        loadLogs(1);
    };

    const handleClearSearch = () => {
        setSearchTerm('');
        setTimeout(() => loadLogs(1), 0);
    };

    const toggleExpandRow = (id: string | number) => {
        setExpandedRowIds((prev) => {
            const next = new Set(prev);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }
            return next;
        });
    };

    const handleClearLogs = async () => {
        setClearing(true);
        try {
            await projectService.clearProjectLogs(projectId);
            setIsClearModalOpen(false);
            loadLogs(1);
            loadLogKeys();
        } catch (err: any) {
            setError(err?.message || 'Failed to clear project logs.');
        } finally {
            setClearing(false);
        }
    };

    // Calculate quick telemetry stats
    const uniqueIps = Array.from(new Set(logs.map((l) => l.ip).filter(Boolean)));
    const latestLogTime = logs.length > 0 ? logs[0].created_at : null;

    return {
        project,
        logs,
        availableKeys,
        loading,
        error,
        clearing,
        isClearModalOpen,
        setIsClearModalOpen,
        searchTerm,
        setSearchTerm,
        selectedKey,
        setSelectedKey,
        dateFrom,
        setDateFrom,
        dateTo,
        setDateTo,
        page,
        setPage,
        perPage,
        setPerPage,
        total,
        autoRefreshInterval,
        setAutoRefreshInterval,
        selectedLogForModal,
        setSelectedLogForModal,
        expandedRowIds,
        uniqueIps,
        latestLogTime,
        loadLogs,
        handleSearchSubmit,
        handleClearSearch,
        toggleExpandRow,
        handleClearLogs,
    };
}
