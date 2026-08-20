import React, { useEffect, useState } from 'react';
import { LogFile, LogItem, LogLevelCount, LogPagination } from '../../model/log_model';
import { LogService } from '../../services/log_service';
import { PageTitle } from '../../shared/widget/page_title';
import { CopyButton } from '../../shared/widget/copy_button';
import { renderLogLevelBadge } from '../../shared/widget/badge';
import {
    IconChevronDown,
    IconChevronRight,
    IconDownload,
    IconRefresh,
    IconSearch,
    IconTrash,
    IconX,
} from '../../shared/widget/icons';

const ALL_LOG_LEVELS = [
    'EMERGENCY',
    'ALERT',
    'CRITICAL',
    'ERROR',
    'WARNING',
    'NOTICE',
    'INFO',
    'DEBUG',
];

const LOG_LEVEL_OPTIONS = [
    { label: 'All Levels', value: 'ALL' },
    { label: 'Emergency', value: 'EMERGENCY' },
    { label: 'Alert', value: 'ALERT' },
    { label: 'Critical', value: 'CRITICAL' },
    { label: 'Error', value: 'ERROR' },
    { label: 'Warning', value: 'WARNING' },
    { label: 'Notice', value: 'NOTICE' },
    { label: 'Info', value: 'INFO' },
    { label: 'Debug', value: 'DEBUG' },
];

export function LogsPage(): React.JSX.Element {
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

    // 1. Initial Load: Fetch Log Files list
    const loadFiles = async () => {
        try {
            const fileList = await LogService.getFiles();
            setFiles(fileList);
            if (fileList.length > 0 && !selectedFile) {
                const defaultFile =
                    fileList.find((f) => f.name === 'laravel.log') || fileList[0];
                setSelectedFile(defaultFile.identifier);
            }
        } catch (err: any) {
            setError(err?.message || 'Failed to load log files.');
        }
    };

    useEffect(() => {
        loadFiles();
    }, []);

    // 2. Fetch Logs when file, filters, or page changes
    const fetchLogs = async (pageNum = page) => {
        if (!selectedFile) return;
        setLoading(true);
        setError('');
        try {
            const exclude_levels =
                selectedLevel !== 'ALL'
                    ? ALL_LOG_LEVELS.filter((lvl) => lvl !== selectedLevel)
                    : undefined;

            const res = await LogService.getLogs({
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
            await LogService.deleteFile(selectedFile);
            setSelectedFile('');
            await loadFiles();
        } catch (err: any) {
            alert(err?.message || 'Failed to delete file.');
        }
    };

    const isFiltered = searchTerm.trim() !== '' || selectedLevel !== 'ALL';

    return (
        <>
            <PageTitle
                eyebrow="System Logs"
                title="Application Log Viewer"
                subtitle="Live inspection, searching, and stack-trace auditing for Laravel, FrankenPHP, and background queues."
            >
                <div className="toolbar">
                    {performance?.requestTime && (
                        <span className="filter-badge" style={{ fontSize: '11px', color: 'var(--text-subtle)' }}>
                            {performance.requestTime} • {performance.memoryUsage} {percentScanned !== undefined ? `(${percentScanned}% scanned)` : ''}
                        </span>
                    )}
                    {currentFileObj && (
                        <button
                            type="button"
                            className="button"
                            onClick={() => window.open(currentFileObj.download_url, '_blank')}
                            title="Download current log file"
                        >
                            <IconDownload /> Download File
                        </button>
                    )}
                    <button
                        type="button"
                        className="button"
                        onClick={() => fetchLogs(page)}
                        title="Reload logs"
                        disabled={loading}
                    >
                        <IconRefresh /> Refresh
                    </button>
                </div>
            </PageTitle>

            <div className="panel" style={{ marginBottom: '20px' }}>
                {/* Top Filter Bar */}
                <div className="filter-bar">
                    {/* Log File Selector */}
                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px', flex: '1 1 280px' }}>
                        <span className="label" style={{ whiteSpace: 'nowrap' }}>File:</span>
                        <select
                            className="filter-select"
                            style={{ flex: 1, fontWeight: 600 }}
                            value={selectedFile}
                            onChange={(e) => {
                                setSelectedFile(e.target.value);
                                setPage(1);
                            }}
                        >
                            {files.map((file) => (
                                <option value={file.identifier} key={file.identifier}>
                                    {file.name} ({file.size_formatted})
                                </option>
                            ))}
                        </select>
                    </div>

                    {/* Search query input */}
                    <form className="search-box" onSubmit={handleSearchSubmit}>
                        <span className="search-icon"><IconSearch /></span>
                        <input
                            className="search-input"
                            type="text"
                            placeholder="Search log messages or exceptions..."
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                        />
                        {searchTerm && (
                            <button className="search-clear" type="button" onClick={handleClearSearch}>
                                <IconX />
                            </button>
                        )}
                    </form>

                    {/* Level selector */}
                    <div className="filter-group">
                        <select
                            className="filter-select"
                            value={selectedLevel}
                            onChange={(e) => {
                                setSelectedLevel(e.target.value);
                                setPage(1);
                            }}
                        >
                            {LOG_LEVEL_OPTIONS.map((lvl) => (
                                <option value={lvl.value} key={lvl.value}>
                                    {lvl.label}
                                </option>
                            ))}
                        </select>

                        {/* Direction / Sort */}
                        <select
                            className="filter-select"
                            value={direction}
                            onChange={(e) => setDirection(e.target.value as 'desc' | 'asc')}
                        >
                            <option value="desc">Newest First</option>
                            <option value="asc">Oldest First</option>
                        </select>

                        {/* Per Page */}
                        <select
                            className="filter-select"
                            value={perPage}
                            onChange={(e) => {
                                setPerPage(Number(e.target.value));
                                setPage(1);
                            }}
                        >
                            <option value="15">15 / page</option>
                            <option value="25">25 / page</option>
                            <option value="50">50 / page</option>
                            <option value="100">100 / page</option>
                        </select>

                        {isFiltered && (
                            <button
                                className="button"
                                type="button"
                                onClick={() => {
                                    setSearchTerm('');
                                    setSelectedLevel('ALL');
                                    setTimeout(() => fetchLogs(1), 0);
                                }}
                            >
                                <IconX /> Clear Filter
                            </button>
                        )}

                        {currentFileObj?.can_delete && (
                            <button
                                className="button danger"
                                type="button"
                                style={{ minHeight: '34px', padding: '4px 10px', fontSize: '12px' }}
                                onClick={handleDeleteFile}
                                title="Delete log file"
                            >
                                <IconTrash /> Delete
                            </button>
                        )}
                    </div>
                </div>

                {/* Level Quick-Filter Chips */}
                {levelCounts.length > 0 && (
                    <div
                        style={{
                            display: 'flex',
                            gap: '8px',
                            padding: '10px 20px',
                            borderBottom: '1px solid var(--border)',
                            background: 'var(--surface-subtle)',
                            overflowX: 'auto',
                            alignItems: 'center',
                        }}
                    >
                        <span style={{ fontSize: '12px', fontWeight: 600, color: 'var(--text-subtle)', whiteSpace: 'nowrap' }}>
                            Quick Levels:
                        </span>
                        <button
                            type="button"
                            className={`badge ${selectedLevel === 'ALL' ? 'active' : ''}`}
                            style={{
                                cursor: 'pointer',
                                background: selectedLevel === 'ALL' ? 'var(--primary)' : 'var(--surface)',
                                color: selectedLevel === 'ALL' ? '#ffffff' : 'var(--text)',
                                border: '1px solid var(--border)',
                                padding: '4px 10px',
                                borderRadius: '999px',
                                fontSize: '12px',
                                fontWeight: 500,
                            }}
                            onClick={() => {
                                setSelectedLevel('ALL');
                                setPage(1);
                            }}
                        >
                            All ({pagination?.total ?? 0})
                        </button>
                        {levelCounts.map((lc) => {
                            const isSelected = selectedLevel === lc.level;
                            return (
                                <button
                                    key={lc.level}
                                    type="button"
                                    className={`badge ${isSelected ? 'active' : ''}`}
                                    style={{
                                        cursor: 'pointer',
                                        background: isSelected ? 'var(--primary)' : 'var(--surface)',
                                        color: isSelected ? '#ffffff' : 'var(--text)',
                                        border: '1px solid var(--border)',
                                        padding: '4px 10px',
                                        borderRadius: '999px',
                                        fontSize: '12px',
                                        fontWeight: 500,
                                    }}
                                    onClick={() => {
                                        setSelectedLevel(isSelected ? 'ALL' : lc.level);
                                        setPage(1);
                                    }}
                                >
                                    {lc.level_name} ({lc.count})
                                </button>
                            );
                        })}
                    </div>
                )}

                {/* Error Banner */}
                {error && (
                    <div style={{ padding: '16px 20px' }}>
                        <div className="alert">{error}</div>
                    </div>
                )}

                {/* Log List / Table */}
                <div className="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th style={{ width: '40px' }}></th>
                                <th style={{ width: '120px' }}>Level</th>
                                <th style={{ width: '170px' }}>Timestamp</th>
                                <th style={{ width: '110px' }}>Environment</th>
                                <th>Message</th>
                            </tr>
                        </thead>
                        <tbody>
                            {loading ? (
                                <tr>
                                    <td colSpan={5} className="empty" style={{ padding: '36px', textAlign: 'center' }}>
                                        Loading log events...
                                    </td>
                                </tr>
                            ) : logs.length > 0 ? (
                                logs.map((log) => {
                                    const isExpanded = Boolean(expandedRows[log.index]);
                                    const hasDetails = Boolean(log.context || log.full_text || log.message.length > 120);

                                    return (
                                        <React.Fragment key={`${log.file_identifier}-${log.index}`}>
                                            <tr
                                                onClick={() => hasDetails && toggleRow(log.index)}
                                                style={{ cursor: hasDetails ? 'pointer' : 'default' }}
                                            >
                                                <td style={{ textAlign: 'center', color: 'var(--text-subtle)' }}>
                                                    {hasDetails ? (
                                                        isExpanded ? <IconChevronDown /> : <IconChevronRight />
                                                    ) : null}
                                                </td>
                                                <td>{renderLogLevelBadge(log.level)}</td>
                                                <td className="mono" style={{ whiteSpace: 'nowrap', fontSize: '12px' }}>
                                                    {log.datetime || log.time}
                                                </td>
                                                <td>
                                                    <span className="filter-badge" style={{ fontSize: '11px', textTransform: 'uppercase' }}>
                                                        {log.extra?.environment || 'laravel'}
                                                    </span>
                                                </td>
                                                <td style={{ maxWidth: '600px' }}>
                                                    <div
                                                        style={{
                                                            overflow: 'hidden',
                                                            textOverflow: 'ellipsis',
                                                            whiteSpace: isExpanded ? 'normal' : 'nowrap',
                                                            fontWeight: log.level === 'ERROR' ? 600 : 400,
                                                        }}
                                                    >
                                                        {log.message}
                                                    </div>
                                                </td>
                                            </tr>
                                            {isExpanded && (
                                                <tr>
                                                    <td colSpan={5} style={{ background: 'var(--surface-subtle)', padding: '16px 24px' }}>
                                                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' }}>
                                                            <strong style={{ fontSize: '13px', color: 'var(--text)' }}>
                                                                Stack Trace & Context Payload:
                                                            </strong>
                                                            <CopyButton text={log.full_text || log.message} />
                                                        </div>
                                                        <pre
                                                            className="mono"
                                                            style={{
                                                                margin: 0,
                                                                padding: '14px',
                                                                background: 'var(--bg-page)',
                                                                border: '1px solid var(--border)',
                                                                borderRadius: 'var(--radius-md)',
                                                                whiteSpace: 'pre-wrap',
                                                                maxHeight: '340px',
                                                                overflowY: 'auto',
                                                                fontSize: '12px',
                                                                lineHeight: 1.5,
                                                                color: 'var(--text)',
                                                            }}
                                                        >
                                                            {log.full_text || log.message}
                                                            {log.context && Object.keys(log.context).length > 0 && (
                                                                `\n\n--- Context ---\n${JSON.stringify(log.context, null, 2)}`
                                                            )}
                                                        </pre>
                                                    </td>
                                                </tr>
                                            )}
                                        </React.Fragment>
                                    );
                                })
                            ) : (
                                <tr>
                                    <td colSpan={5} className="empty" style={{ padding: '36px', textAlign: 'center' }}>
                                        {isFiltered ? 'No log entries match your filter criteria.' : 'No log entries found in this file.'}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Pagination */}
            {pagination && (
                <div className="pagination">
                    <div>
                        Showing {pagination.from || 0} to {pagination.to || 0} of {pagination.total} log events
                    </div>
                    <div className="pagination-actions">
                        <button
                            className="button"
                            disabled={pagination.current_page <= 1 || loading}
                            onClick={() => fetchLogs(pagination.current_page - 1)}
                        >
                            Previous
                        </button>
                        <span className="button">
                            Page {pagination.current_page} of {pagination.last_page}
                        </span>
                        <button
                            className="button"
                            disabled={pagination.current_page >= pagination.last_page || loading}
                            onClick={() => fetchLogs(pagination.current_page + 1)}
                        >
                            Next
                        </button>
                    </div>
                </div>
            )}
        </>
    );
}
