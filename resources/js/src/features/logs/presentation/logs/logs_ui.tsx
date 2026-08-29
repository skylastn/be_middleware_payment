import React from 'react';
import { PageTitle } from '@/shared/component/ui/page_title';
import { CopyButton } from '@/shared/component/ui/copy_button';
import { renderLogLevelBadge } from '@/shared/component/ui/badge';
import {
    IconChevronDown,
    IconChevronRight,
    IconDownload,
    IconRefresh,
    IconSearch,
    IconTrash,
    IconX,
} from '@/shared/component/ui/icons';
import { SkeletonTableRows } from '@/shared/component/ui/skeleton';
import { LOG_LEVEL_OPTIONS } from '../../domain/model/enum/log_level';
import { useLogsLogic } from './logs_logic';

export function LogsPage(): React.JSX.Element {
    const {
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
    } = useLogsLogic();

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
                                <SkeletonTableRows rows={perPage > 15 ? 10 : 6} columns={5} />
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
