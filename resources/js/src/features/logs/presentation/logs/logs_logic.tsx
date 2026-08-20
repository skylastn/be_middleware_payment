import { useEffect, useState } from 'react';
import { LogFile } from '../../domain/model/response/log_file_response';
import { LogItem, LogLevelCount, LogPagination } from '../../domain/model/response/log_response';
import { ALL_LOG_LEVELS } from '../../domain/model/enum/log_level';
import { logService } from '../../application/log_service';

export function useLogsLogic() {
    const [files, setFiles] = useState<LogFile[]>([]);
    const [selectedFile, setSelectedFile] = useState<string>('');
    const [logs, setLogs] = useState<LogItem[]>([]);
    const [levelCounts, setLevelCounts] = useState<LogLevelCount[]>([]);
    const [pagination, setPagination] = useState<LogPagination | null>(null);
    const [performance, setPerformance] = useState<{ memoryUsage?: string; requestTime?: string } | null>(null);
    const [percentScanned, setPercentScanned] = useState<number | undefined>(undefined);
    const [loading, setLoading] = useState<boolean>(true);
    const [error, setError] = useState<string>('');
    const [expandedRows, setExpandedRows] = useState<Record<number, boolean>>({});

    // Filter states
    const [searchTerm, setSearchTerm] = useState<string>('');
    const [selectedLevel, setSelectedLevel] = useState<string>('ALL');
    const [perPage, setPerPage] = useState<number>(25);
    const [page, setPage] = useState<number>(1);
    const [direction, setDirection] = useState<'desc' | 'asc'>('desc');

    const loadFiles = async () => {
        try {
            const fileList = await logService.getFiles();
            setFiles(fileList);
            if (fileList.length > 0 && !selectedFile) {
                const defaultFile = fileList.find((f) => f.name === 'laravel.log') || fileList[0];
                setSelectedFile(defaultFile.identifier);
            }
        } catch (err: any) {
            setError(err?.message || 'Failed to load log files.');
        }
    };

    useEffect(() => {
        loadFiles();
    }, []);

    const fetchLogs = async (pageNum = page) => {
        if (!selectedFile) return;
        setLoading(true);
        setError('');
        try {
            const exclude_levels =
                selectedLevel !== 'ALL'
                    ? ALL_LOG_LEVELS.filter((lvl) => lvl !== selectedLevel)
                    : undefined;

            const res = await logService.getLogs({
                file: selectedFile,
                query: searchTerm.trim() || undefined,
                exclude_levels,
                page: pageNum,
                per_page: perPage,
                direction,
            });

            setLogs(res.logs || []);
            setPagination(res.pagination || null);
            setLevelCounts(res.levelCounts || []);
            setPerformance(res.performance || null);
            setPercentScanned(res.percentScanned);
            setPage(pageNum);
            setExpandedRows({});
        } catch (err: any) {
            setError(err?.message || 'Failed to load logs.');
            setLogs([]);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (selectedFile) {
            fetchLogs(1);
        }
    }, [selectedFile, selectedLevel, perPage, direction]);

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        fetchLogs(1);
    };

    const handleClearSearch = () => {
        setSearchTerm('');
        if (selectedFile) {
            setTimeout(() => fetchLogs(1), 0);
        }
    };

    const toggleRow = (index: number) => {
        setExpandedRows((prev) => ({ ...prev, [index]: !prev[index] }));
    };

    const currentFileObj = files.find((f) => f.identifier === selectedFile);

    const handleDeleteFile = async () => {
        if (!selectedFile) return;
        if (!window.confirm(`Are you sure you want to permanently delete ${currentFileObj?.name}?`)) return;
        try {
            await logService.deleteFile(selectedFile);
            setSelectedFile('');
            await loadFiles();
        } catch (err: any) {
            alert(err?.message || 'Failed to delete file.');
        }
    };

    const isFiltered = searchTerm.trim() !== '' || selectedLevel !== 'ALL';

    return {
        files,
        selectedFile,
        setSelectedFile,
        logs,
        levelCounts,
        pagination,
        performance,
        percentScanned,
        loading,
        error,
        expandedRows,
        searchTerm,
        setSearchTerm,
        selectedLevel,
        setSelectedLevel,
        perPage,
        setPerPage,
        page,
        setPage,
        direction,
        setDirection,
        currentFileObj,
        isFiltered,
        fetchLogs,
        handleSearchSubmit,
        handleClearSearch,
        toggleRow,
        handleDeleteFile,
    };
}
