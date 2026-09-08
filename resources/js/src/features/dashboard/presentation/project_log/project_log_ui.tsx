import React from 'react';
import { PageTitle } from '@/shared/component/ui/page_title';
import { StatCard } from '@/shared/component/ui/stat_card';
import { DataTable } from '@/shared/component/ui/data_table';
import { CopyButton } from '@/shared/component/ui/copy_button';
import { ModalDialog } from '@/shared/component/ui/modal_dialog';
import {
    IconArrowLeft,
    IconChevronDown,
    IconChevronRight,
    IconRefresh,
    IconSearch,
    IconTrash,
    IconX,
} from '@/shared/component/ui/icons';
import { SkeletonTableRows, SkeletonStatsGrid } from '@/shared/component/ui/skeleton';
import { formatDate, navigate } from '@/shared/utils/format_utils';
import { useProjectLogLogic } from './project_log_logic';
import { ProjectLogItem } from '../../domain/model/response/project/project_log_response';

export interface ProjectLogPageProps {
    projectId: string | number;
}

function renderEventBadge(key: string): React.JSX.Element {
    const lower = (key || '').toLowerCase();
    let badgeClass = 'badge info';

    if (lower.includes('error') || lower.includes('fail')) {
        badgeClass = 'badge danger';
    } else if (lower.includes('success') || lower.includes('complete') || lower.includes('paid')) {
        badgeClass = 'badge success';
    } else if (lower.includes('callback') || lower.includes('webhook')) {
        badgeClass = 'badge blue';
    } else if (lower.includes('idempotent') || lower.includes('warn') || lower.includes('retry')) {
        badgeClass = 'badge warning';
    }

    return <span className={badgeClass}>{key || 'event'}</span>;
}

function formatJsonValue(value: any): string {
    if (value === null || value === undefined) return '';
    if (typeof value === 'object') {
        return JSON.stringify(value, null, 2);
    }
    if (typeof value === 'string') {
        try {
            const parsed = JSON.parse(value);
            return JSON.stringify(parsed, null, 2);
        } catch {
            return value;
        }
    }
    return String(value);
}

function getPayloadPreview(value: any): string {
    if (value === null || value === undefined) return '(empty)';
    if (typeof value === 'object') {
        return JSON.stringify(value).slice(0, 110);
    }
    return String(value).replace(/\s+/g, ' ').slice(0, 110);
}

export function ProjectLogPage({ projectId }: ProjectLogPageProps): React.JSX.Element {
    const {
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
        page,
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
    } = useProjectLogLogic({ projectId });

    return (
        <>
            <PageTitle
                eyebrow="Project Diagnostics"
                title={project ? `Logs: ${project.name}` : `Project #${projectId} Logs`}
                subtitle="Dedicated real-time transaction event logs, webhook payloads, and merchant callback audit trail."
            >
                <div className="toolbar">
                    <button
                        type="button"
                        className="button"
                        onClick={() => navigate('/admin/projects')}
                    >
                        <IconArrowLeft /> Back to Projects
                    </button>

                    {/* Auto-refresh control */}
                    <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                        <span className="label" style={{ fontSize: '11px', margin: 0 }}>Auto-Sync:</span>
                        <select
                            className="filter-select"
                            style={{ fontSize: '12px', padding: '5px 10px' }}
                            value={autoRefreshInterval}
                            onChange={(e) => setAutoRefreshInterval(Number(e.target.value))}
                        >
                            <option value="0">Off</option>
                            <option value="5">5s Live</option>
                            <option value="10">10s</option>
                            <option value="30">30s</option>
                        </select>
                    </div>

                    <button
                        type="button"
                        className="button"
                        onClick={() => loadLogs(page)}
                        disabled={loading}
                        title="Reload logs"
                    >
                        <IconRefresh /> Refresh
                    </button>

                    <button
                        type="button"
                        className="button danger"
                        onClick={() => setIsClearModalOpen(true)}
                        disabled={loading || total === 0}
                        title="Truncate this project's log table"
                    >
                        <IconTrash /> Clear Logs
                    </button>
                </div>
            </PageTitle>

            {error && <div className="panel alert danger" style={{ marginBottom: '16px' }}>{error}</div>}

            {/* Standard Stats Grid */}
            {loading && !project ? (
                <div style={{ marginBottom: '20px' }}>
                    <SkeletonStatsGrid count={4} />
                </div>
            ) : (
                <section className="grid stats" style={{ marginBottom: '20px' }}>
                    <div className="panel stat blue" style={{ display: 'flex', flexDirection: 'column', justifyContent: 'space-between', padding: '18px 20px' }}>
                        <div>
                            <div className="stat-label">Target Project</div>
                            <div style={{ marginTop: '8px', display: 'flex', alignItems: 'center', gap: '8px', flexWrap: 'wrap' }}>
                                <strong style={{ fontSize: '17px', color: 'var(--text)', fontWeight: 700 }}>
                                    {project?.name || `Project #${projectId}`}
                                </strong>
                                {project?.type && <span className="badge">{project.type}</span>}
                                {project?.slug && <span className="badge blue">{project.slug}</span>}
                            </div>
                        </div>
                        <div className="stat-note mono" style={{ fontSize: '11px', display: 'flex', alignItems: 'center', gap: '6px', marginTop: '10px' }}>
                            <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', maxWidth: '280px' }}>
                                {project?.callback || 'No callback URL configured'}
                            </span>
                            {project?.callback && <CopyButton text={project.callback} />}
                        </div>
                    </div>

                    <StatCard
                        label="Total Log Records"
                        value={total}
                        note={`Indexed in z__log__${projectId}`}
                        tone="blue"
                    />

                    <StatCard
                        label="Event Types"
                        value={availableKeys.length}
                        note={availableKeys.length > 0 ? `${availableKeys.slice(0, 2).join(', ')}${availableKeys.length > 2 ? ` +${availableKeys.length - 2} more` : ''}` : 'No events registered'}
                        tone="purple"
                    />

                    <StatCard
                        label="Unique Client IPs"
                        value={uniqueIps.length}
                        note={latestLogTime ? `Last: ${formatDate(latestLogTime)}` : 'No logs recorded'}
                        tone="warning"
                    />
                </section>
            )}

            {/* Filter & Data Table Panel */}
            <div className="panel" style={{ marginBottom: '20px' }}>
                <div className="filter-bar">
                    <form className="search-box" onSubmit={handleSearchSubmit}>
                        <span className="search-icon"><IconSearch /></span>
                        <input
                            className="search-input"
                            type="text"
                            placeholder="Search log key, payload JSON, or IP address..."
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                        />
                        {searchTerm && (
                            <button className="search-clear" type="button" onClick={handleClearSearch}>
                                <IconX />
                            </button>
                        )}
                    </form>

                    <div className="filter-group">
                        <select
                            className="filter-select"
                            value={selectedKey}
                            onChange={(e) => setSelectedKey(e.target.value)}
                        >
                            <option value="all">All Event Keys ({availableKeys.length})</option>
                            {availableKeys.map((k) => (
                                <option key={k} value={k}>
                                    {k}
                                </option>
                            ))}
                        </select>

                        <select
                            className="filter-select"
                            value={perPage}
                            onChange={(e) => setPerPage(Number(e.target.value))}
                        >
                            <option value="10">10 / page</option>
                            <option value="20">20 / page</option>
                            <option value="50">50 / page</option>
                            <option value="100">100 / page</option>
                        </select>
                    </div>
                </div>

                <DataTable columns={['', 'ID', 'Event Key', 'Payload Preview', 'Client IP', 'Timestamp', 'Actions']}>
                    {loading ? (
                        <SkeletonTableRows rows={perPage > 15 ? 10 : 6} columns={7} />
                    ) : logs.length > 0 ? (
                        logs.map((log: ProjectLogItem) => {
                            const isExpanded = expandedRowIds.has(log.id);
                            const formattedPayload = formatJsonValue(log.value);
                            const oneLineSnippet = getPayloadPreview(log.value);

                            return (
                                <React.Fragment key={log.id}>
                                    <tr style={{ cursor: 'pointer', background: isExpanded ? 'var(--surface-hover)' : undefined }}>
                                        <td style={{ width: '32px', textAlign: 'center' }} onClick={() => toggleExpandRow(log.id)}>
                                            <button
                                                type="button"
                                                className="button ghost-btn"
                                                style={{ padding: '4px', border: 'none', background: 'transparent' }}
                                                title={isExpanded ? 'Collapse' : 'Expand payload'}
                                            >
                                                {isExpanded ? <IconChevronDown /> : <IconChevronRight />}
                                            </button>
                                        </td>
                                        <td className="mono" style={{ width: '70px' }} onClick={() => toggleExpandRow(log.id)}>
                                            <span className="badge blue">{log.id}</span>
                                        </td>
                                        <td onClick={() => toggleExpandRow(log.id)}>
                                            {renderEventBadge(log.key)}
                                        </td>
                                        <td onClick={() => toggleExpandRow(log.id)} style={{ maxWidth: '420px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                                            <code className="mono" style={{ fontSize: '12px', color: 'var(--text-muted)' }}>
                                                {oneLineSnippet}
                                            </code>
                                        </td>
                                        <td>
                                            <div style={{ display: 'flex', alignItems: 'center', gap: '4px' }}>
                                                <span className="mono" style={{ fontSize: '12px' }}>{log.ip || '-'}</span>
                                                {log.ip && <CopyButton text={log.ip} />}
                                            </div>
                                        </td>
                                        <td style={{ whiteSpace: 'nowrap', fontSize: '12px' }}>
                                            {formatDate(log.created_at)}
                                        </td>
                                        <td>
                                            <div className="actions">
                                                <button
                                                    type="button"
                                                    className="button"
                                                    style={{ fontSize: '12px', padding: '4px 10px', fontWeight: 600 }}
                                                    onClick={() => setSelectedLogForModal(log)}
                                                >
                                                    Inspect
                                                </button>
                                                <CopyButton text={formattedPayload} label="Copy" />
                                            </div>
                                        </td>
                                    </tr>

                                    {/* Inline expanded JSON viewer */}
                                    {isExpanded && (
                                        <tr>
                                            <td colSpan={7} style={{ background: 'var(--surface-subtle)', padding: '16px 20px', borderTop: '1px solid var(--border)' }}>
                                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' }}>
                                                    <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
                                                        <span className="label" style={{ margin: 0 }}>Payload Data:</span>
                                                        {renderEventBadge(log.key)}
                                                        <span className="mono muted" style={{ fontSize: '11px' }}>IP: {log.ip}</span>
                                                    </div>
                                                    <div style={{ display: 'flex', gap: '8px' }}>
                                                        <button
                                                            type="button"
                                                            className="button"
                                                            style={{ fontSize: '11px', padding: '3px 8px' }}
                                                            onClick={() => setSelectedLogForModal(log)}
                                                        >
                                                            Open Full Modal
                                                        </button>
                                                        <CopyButton text={formattedPayload} label="Copy JSON" />
                                                    </div>
                                                </div>
                                                <pre
                                                    className="mono"
                                                    style={{
                                                        background: 'var(--surface)',
                                                        border: '1px solid var(--border)',
                                                        borderRadius: '6px',
                                                        padding: '12px',
                                                        fontSize: '12px',
                                                        maxHeight: '300px',
                                                        overflowY: 'auto',
                                                        whiteSpace: 'pre-wrap',
                                                        wordBreak: 'break-word',
                                                        margin: 0,
                                                        color: 'var(--text)',
                                                    }}
                                                >
                                                    {formattedPayload}
                                                </pre>
                                            </td>
                                        </tr>
                                    )}
                                </React.Fragment>
                            );
                        })
                    ) : (
                        <tr>
                            <td colSpan={7} className="empty" style={{ padding: '48px 16px', textAlign: 'center' }}>
                                <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '8px' }}>
                                    <span style={{ fontSize: '16px', fontWeight: 600 }}>No logs found</span>
                                    <p className="muted" style={{ maxWidth: '400px', margin: 0, fontSize: '13px' }}>
                                        {searchTerm || selectedKey !== 'all'
                                            ? 'No logs matched the current search filters. Try clearing your filters.'
                                            : 'No activity logs have been recorded for this project yet.'}
                                    </p>
                                    {(searchTerm || selectedKey !== 'all') && (
                                        <button
                                            type="button"
                                            className="button"
                                            style={{ marginTop: '8px' }}
                                            onClick={() => {
                                                setSearchTerm('');
                                                setSelectedKey('all');
                                            }}
                                        >
                                            Reset Filters
                                        </button>
                                    )}
                                </div>
                            </td>
                        </tr>
                    )}
                </DataTable>
            </div>

            {/* Pagination Controls */}
            {total > perPage && (
                <div className="pagination">
                    <div>
                        Showing page {page} of {Math.ceil(total / perPage)} ({total.toLocaleString()} total entries)
                    </div>
                    <div className="pagination-actions">
                        <button
                            type="button"
                            className="button"
                            disabled={page <= 1 || loading}
                            onClick={() => loadLogs(page - 1)}
                        >
                            Previous
                        </button>
                        <span className="button">Page {page}</span>
                        <button
                            type="button"
                            className="button"
                            disabled={page >= Math.ceil(total / perPage) || loading}
                            onClick={() => loadLogs(page + 1)}
                        >
                            Next
                        </button>
                    </div>
                </div>
            )}

            {/* Detailed Payload Inspector Modal */}
            {selectedLogForModal && (
                <ModalDialog
                    isOpen={Boolean(selectedLogForModal)}
                    title={`Log Entry #${selectedLogForModal.id}`}
                    description={`Recorded at ${formatDate(selectedLogForModal.created_at)} from IP ${selectedLogForModal.ip || 'UNKNOWN'}`}
                    confirmText="Close Inspector"
                    confirmTone="primary"
                    maxWidth="720px"
                    onConfirm={() => setSelectedLogForModal(null)}
                    onClose={() => setSelectedLogForModal(null)}
                >
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '14px' }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '8px' }}>
                            <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
                                <span className="label" style={{ margin: 0 }}>Event Key:</span>
                                {renderEventBadge(selectedLogForModal.key)}
                            </div>
                            <CopyButton text={formatJsonValue(selectedLogForModal.value)} label="Copy Formatted JSON" />
                        </div>

                        <div className="field">
                            <span className="label">Formatted Payload / Data:</span>
                            <pre
                                className="mono"
                                style={{
                                    background: 'var(--surface-subtle)',
                                    border: '1px solid var(--border)',
                                    borderRadius: '6px',
                                    padding: '14px',
                                    fontSize: '12px',
                                    maxHeight: '420px',
                                    overflowY: 'auto',
                                    whiteSpace: 'pre-wrap',
                                    wordBreak: 'break-word',
                                    color: 'var(--text)',
                                    margin: 0,
                                }}
                            >
                                {formatJsonValue(selectedLogForModal.value)}
                            </pre>
                        </div>
                    </div>
                </ModalDialog>
            )}

            {/* Clear Logs Confirmation Modal */}
            <ModalDialog
                isOpen={isClearModalOpen}
                title="Clear All Project Logs?"
                description={`This will permanently truncate table z__log__${projectId} and delete all ${total.toLocaleString()} log records for "${project?.name || `Project #${projectId}`}". This action cannot be undone.`}
                confirmText={clearing ? 'Clearing Logs...' : 'Yes, Delete All Logs'}
                cancelText="Cancel"
                confirmTone="danger"
                loading={clearing}
                onConfirm={handleClearLogs}
                onClose={() => setIsClearModalOpen(false)}
            />
        </>
    );
}
