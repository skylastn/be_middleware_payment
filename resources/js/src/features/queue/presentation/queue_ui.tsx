import React from 'react';
import { PageTitle } from '@/shared/component/ui/page_title';
import { StatCard } from '@/shared/component/ui/stat_card';
import { ModalDialog } from '@/shared/component/ui/modal_dialog';
import { CopyButton } from '@/shared/component/ui/copy_button';
import {
    IconCheck,
    IconHistory,
    IconPlus,
    IconQueue,
    IconRefresh,
    IconSearch,
    IconTrash,
    IconX,
} from '@/shared/component/ui/icons';
import { formatDate } from '@/shared/utils/format_utils';
import { FailedJobItem, QueueJobItem } from '../domain/model/queue_model';
import { useQueueLogic } from './queue_logic';

export function QueuePage(): React.JSX.Element {
    const {
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
    } = useQueueLogic();

    const stats = overview?.stats || {
        pending: 0,
        scheduled: 0,
        reserved: 0,
        failed: 0,
        total_in_queue: 0,
    };

    const isFailedJob = (job: QueueJobItem | FailedJobItem): job is FailedJobItem => {
        return 'exception_full' in job;
    };

    return (
        <div className="page-container" style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
            <PageTitle
                title="Queue Monitor"
                eyebrow="Tools"
                subtitle={`Monitor background job scheduling, in-flight executions, and failures (${overview?.driver?.toUpperCase() || 'REDIS'} / ${overview?.default_queue || 'default'})`}
            >
                <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
                    <button
                        type="button"
                        className={`button ${autoRefresh ? 'primary' : ''}`}
                        style={{ fontSize: '12px', padding: '6px 12px' }}
                        onClick={() => setAutoRefresh(!autoRefresh)}
                    >
                        <IconHistory /> {autoRefresh ? 'Live (5s) ON' : 'Live Sync'}
                    </button>

                    <button
                        type="button"
                        className="button"
                        style={{ fontSize: '12px', padding: '6px 12px' }}
                        onClick={refreshAll}
                        disabled={refreshing}
                    >
                        <IconRefresh /> {refreshing ? 'Syncing...' : 'Refresh'}
                    </button>

                    <button
                        type="button"
                        className="button primary"
                        style={{ fontSize: '12px', padding: '6px 14px' }}
                        onClick={() => setDispatchModalOpen(true)}
                    >
                        <IconPlus /> Test Dispatch
                    </button>
                </div>
            </PageTitle>

            {actionMessage && (
                <div
                    className="panel"
                    style={{
                        padding: '10px 16px',
                        backgroundColor: 'var(--bg-accent, #f0fdf4)',
                        borderColor: 'var(--border-accent, #bbf7d0)',
                        color: 'var(--text-accent, #166534)',
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'center',
                        fontSize: '13px',
                    }}
                >
                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                        <IconCheck />
                        <span>{actionMessage}</span>
                    </div>
                    <button
                        type="button"
                        className="button ghost-btn"
                        style={{ padding: '2px 6px' }}
                        onClick={() => setActionMessage(null)}
                    >
                        <IconX />
                    </button>
                </div>
            )}

            {/* Metric Stat Cards */}
            <div className="grid-responsive-4" style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '14px' }}>
                <StatCard
                    label="Pending in Queue"
                    value={stats.pending}
                    note="Ready for workers to execute"
                    tone="accent"
                    icon={<IconQueue />}
                />
                <StatCard
                    label="Scheduled / Delayed"
                    value={stats.scheduled}
                    note="Waiting for target execution time"
                    tone="warning"
                    icon={<IconHistory />}
                />
                <StatCard
                    label="In-Flight / Processing"
                    value={stats.reserved}
                    note="Currently held by active workers"
                    tone="info"
                    icon={<IconRefresh />}
                />
                <StatCard
                    label="Failed Jobs"
                    value={stats.failed}
                    note="Exceeded maximum retries"
                    tone={stats.failed > 0 ? 'danger' : ''}
                    icon={<IconTrash />}
                />
            </div>

            {/* Sub-Tabs */}
            <div className="panel" style={{ padding: '0px', overflow: 'hidden' }}>
                <div
                    style={{
                        display: 'flex',
                        borderBottom: '1px solid var(--border-color)',
                        backgroundColor: 'var(--bg-subtle)',
                        padding: '0 16px',
                        gap: '8px',
                    }}
                >
                    <button
                        type="button"
                        className={`button ghost-btn ${tab === 'active' ? 'active' : ''}`}
                        style={{
                            borderRadius: '0',
                            borderBottom: tab === 'active' ? '2px solid var(--primary-color)' : '2px solid transparent',
                            fontWeight: tab === 'active' ? 700 : 500,
                            padding: '12px 16px',
                        }}
                        onClick={() => setTab('active')}
                    >
                        Active Queue Jobs ({stats.total_in_queue})
                    </button>
                    <button
                        type="button"
                        className={`button ghost-btn ${tab === 'failed' ? 'active' : ''}`}
                        style={{
                            borderRadius: '0',
                            borderBottom: tab === 'failed' ? '2px solid var(--danger-color, #ef4444)' : '2px solid transparent',
                            fontWeight: tab === 'failed' ? 700 : 500,
                            padding: '12px 16px',
                            color: stats.failed > 0 && tab !== 'failed' ? 'var(--danger-color, #ef4444)' : undefined,
                        }}
                        onClick={() => setTab('failed')}
                    >
                        Failed Jobs ({stats.failed})
                    </button>
                    <button
                        type="button"
                        className={`button ghost-btn ${tab === 'info' ? 'active' : ''}`}
                        style={{
                            borderRadius: '0',
                            borderBottom: tab === 'info' ? '2px solid var(--primary-color)' : '2px solid transparent',
                            fontWeight: tab === 'info' ? 700 : 500,
                            padding: '12px 16px',
                        }}
                        onClick={() => setTab('info')}
                    >
                        Driver & Status
                    </button>
                </div>

                {/* Tab 1: Active Jobs */}
                {tab === 'active' && (
                    <div style={{ padding: '16px', display: 'flex', flexDirection: 'column', gap: '14px' }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '10px' }}>
                            <div style={{ display: 'flex', gap: '6px' }}>
                                {(['all', 'pending', 'scheduled', 'reserved'] as const).map((filterType) => (
                                    <button
                                        key={filterType}
                                        type="button"
                                        className={`button ${activeTypeFilter === filterType ? 'primary' : ''}`}
                                        style={{ fontSize: '11px', padding: '4px 10px', textTransform: 'capitalize' }}
                                        onClick={() => setActiveTypeFilter(filterType)}
                                    >
                                        {filterType}
                                    </button>
                                ))}
                            </div>
                            <span style={{ fontSize: '12px', color: 'var(--text-subtle)' }}>
                                Showing {activeJobs.length} active job(s)
                            </span>
                        </div>

                        {loading ? (
                            <div style={{ padding: '40px', textAlign: 'center', color: 'var(--text-subtle)' }}>Loading active jobs...</div>
                        ) : activeJobs.length === 0 ? (
                            <div style={{ padding: '48px', textAlign: 'center', color: 'var(--text-subtle)' }}>
                                <IconCheck /> No {activeTypeFilter === 'all' ? '' : activeTypeFilter} jobs in queue. The queue is idle and healthy.
                            </div>
                        ) : (
                            <div className="table-wrapper" style={{ overflowX: 'auto' }}>
                                <table className="data-table" style={{ width: '100%', borderCollapse: 'collapse', fontSize: '12px' }}>
                                    <thead>
                                        <tr style={{ borderBottom: '1px solid var(--border-color)', textAlign: 'left' }}>
                                            <th style={{ padding: '10px' }}>Status</th>
                                            <th style={{ padding: '10px' }}>Job Name</th>
                                            <th style={{ padding: '10px' }}>Queue</th>
                                            <th style={{ padding: '10px' }}>Attempts</th>
                                            <th style={{ padding: '10px' }}>Scheduled For / Created</th>
                                            <th style={{ padding: '10px', textAlign: 'right' }}>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {activeJobs.map((job) => (
                                            <tr key={job.id} style={{ borderBottom: '1px solid var(--border-color)' }}>
                                                <td style={{ padding: '10px' }}>
                                                    <span
                                                        className={`badge ${
                                                            job.status === 'pending'
                                                                ? 'success'
                                                                : job.status === 'scheduled'
                                                                ? 'warning'
                                                                : 'blue'
                                                        }`}
                                                    >
                                                        {job.status.toUpperCase()}
                                                    </span>
                                                </td>
                                                <td style={{ padding: '10px' }}>
                                                    <div style={{ fontWeight: 600 }}>{job.name}</div>
                                                    <div style={{ fontSize: '10px', fontFamily: 'monospace', color: 'var(--text-subtle)' }}>
                                                        {job.uuid || job.id}
                                                    </div>
                                                </td>
                                                <td style={{ padding: '10px' }}>
                                                    <span style={{ fontFamily: 'monospace', fontSize: '11px' }}>{job.queue}</span>
                                                </td>
                                                <td style={{ padding: '10px' }}>
                                                    {job.attempts} {job.max_tries ? `/ ${job.max_tries}` : ''}
                                                </td>
                                                <td style={{ padding: '10px' }}>
                                                    {job.scheduled_for ? (
                                                        <div>
                                                            <div>{formatDate(job.scheduled_for)}</div>
                                                            <div style={{ fontSize: '10px', color: 'var(--text-subtle)' }}>Delayed execution</div>
                                                        </div>
                                                    ) : job.created_at ? (
                                                        formatDate(job.created_at)
                                                    ) : (
                                                        '-'
                                                    )}
                                                </td>
                                                <td style={{ padding: '10px', textAlign: 'right' }}>
                                                    <button
                                                        type="button"
                                                        className="button ghost-btn"
                                                        style={{ fontSize: '11px', padding: '4px 8px' }}
                                                        onClick={() => setSelectedJob(job)}
                                                    >
                                                        Inspect
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                )}

                {/* Tab 2: Failed Jobs */}
                {tab === 'failed' && (
                    <div style={{ padding: '16px', display: 'flex', flexDirection: 'column', gap: '14px' }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '10px' }}>
                            <div style={{ display: 'flex', gap: '8px', alignItems: 'center', flex: 1, maxWidth: '360px' }}>
                                <div style={{ position: 'relative', width: '100%' }}>
                                    <input
                                        type="text"
                                        placeholder="Search failed job error or payload..."
                                        value={failedSearch}
                                        onChange={(e) => setFailedSearch(e.target.value)}
                                        className="input"
                                        style={{ width: '100%', fontSize: '12px', paddingLeft: '28px' }}
                                    />
                                    <div style={{ position: 'absolute', left: '8px', top: '50%', transform: 'translateY(-50%)', color: 'var(--text-subtle)' }}>
                                        <IconSearch />
                                    </div>
                                </div>
                            </div>

                            {failedJobs.length > 0 && (
                                <div style={{ display: 'flex', gap: '8px' }}>
                                    <button
                                        type="button"
                                        className="button primary"
                                        style={{ fontSize: '11px', padding: '5px 12px' }}
                                        onClick={handleRetryAll}
                                    >
                                        <IconRefresh /> Retry All
                                    </button>
                                    <button
                                        type="button"
                                        className="button danger"
                                        style={{ fontSize: '11px', padding: '5px 12px' }}
                                        onClick={handleFlushFailed}
                                    >
                                        <IconTrash /> Flush All
                                    </button>
                                </div>
                            )}
                        </div>

                        {loading ? (
                            <div style={{ padding: '40px', textAlign: 'center', color: 'var(--text-subtle)' }}>Loading failed jobs...</div>
                        ) : failedJobs.length === 0 ? (
                            <div style={{ padding: '48px', textAlign: 'center', color: 'var(--text-subtle)' }}>
                                <IconCheck /> No failed jobs! All queue jobs executed successfully.
                            </div>
                        ) : (
                            <div className="table-wrapper" style={{ overflowX: 'auto' }}>
                                <table className="data-table" style={{ width: '100%', borderCollapse: 'collapse', fontSize: '12px' }}>
                                    <thead>
                                        <tr style={{ borderBottom: '1px solid var(--border-color)', textAlign: 'left' }}>
                                            <th style={{ padding: '10px' }}>ID</th>
                                            <th style={{ padding: '10px' }}>Job Name</th>
                                            <th style={{ padding: '10px' }}>Queue</th>
                                            <th style={{ padding: '10px' }}>Failed At</th>
                                            <th style={{ padding: '10px' }}>Exception Summary</th>
                                            <th style={{ padding: '10px', textAlign: 'right' }}>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {failedJobs.map((job) => (
                                            <tr key={job.id} style={{ borderBottom: '1px solid var(--border-color)' }}>
                                                <td style={{ padding: '10px', fontFamily: 'monospace', fontSize: '11px' }}>
                                                    #{job.id}
                                                </td>
                                                <td style={{ padding: '10px' }}>
                                                    <div style={{ fontWeight: 600 }}>{job.name}</div>
                                                    <div style={{ fontSize: '10px', fontFamily: 'monospace', color: 'var(--text-subtle)' }}>
                                                        {job.uuid}
                                                    </div>
                                                </td>
                                                <td style={{ padding: '10px', fontFamily: 'monospace' }}>
                                                    {job.queue}
                                                </td>
                                                <td style={{ padding: '10px', whiteSpace: 'nowrap' }}>
                                                    {formatDate(job.failed_at)}
                                                </td>
                                                <td style={{ padding: '10px', maxWidth: '320px' }}>
                                                    <span style={{ color: 'var(--danger-color, #ef4444)', fontFamily: 'monospace', fontSize: '11px' }}>
                                                        {job.exception_summary}
                                                    </span>
                                                </td>
                                                <td style={{ padding: '10px', textAlign: 'right', whiteSpace: 'nowrap' }}>
                                                    <div style={{ display: 'inline-flex', gap: '4px' }}>
                                                        <button
                                                            type="button"
                                                            className="button ghost-btn"
                                                            style={{ fontSize: '11px', padding: '4px 8px' }}
                                                            onClick={() => setSelectedJob(job)}
                                                        >
                                                            Details
                                                        </button>
                                                        <button
                                                            type="button"
                                                            className="button"
                                                            style={{ fontSize: '11px', padding: '4px 8px' }}
                                                            onClick={() => handleRetryJob(job.id)}
                                                        >
                                                            Retry
                                                        </button>
                                                        <button
                                                            type="button"
                                                            className="button ghost-btn"
                                                            style={{ fontSize: '11px', padding: '4px 8px', color: 'var(--danger-color, #ef4444)' }}
                                                            onClick={() => handleForgetJob(job.id)}
                                                        >
                                                            <IconTrash />
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                )}

                {/* Tab 3: Driver & Status */}
                {tab === 'info' && (
                    <div style={{ padding: '20px', display: 'flex', flexDirection: 'column', gap: '16px' }}>
                        <h4 style={{ margin: 0, fontSize: '14px', fontWeight: 600 }}>Queue Driver Information</h4>
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '12px' }}>
                            <div className="panel" style={{ padding: '12px' }}>
                                <div style={{ fontSize: '11px', color: 'var(--text-subtle)' }}>Default Driver</div>
                                <div style={{ fontSize: '15px', fontWeight: 700, textTransform: 'uppercase' }}>
                                    {overview?.driver || 'REDIS'}
                                </div>
                            </div>
                            <div className="panel" style={{ padding: '12px' }}>
                                <div style={{ fontSize: '11px', color: 'var(--text-subtle)' }}>Default Queue Name</div>
                                <div style={{ fontSize: '15px', fontWeight: 700, fontFamily: 'monospace' }}>
                                    {overview?.default_queue || 'default'}
                                </div>
                            </div>
                            {overview?.details && Object.entries(overview.details).map(([key, val]) => (
                                <div key={key} className="panel" style={{ padding: '12px' }}>
                                    <div style={{ fontSize: '11px', color: 'var(--text-subtle)', textTransform: 'capitalize' }}>
                                        {key.replace(/_/g, ' ')}
                                    </div>
                                    <div style={{ fontSize: '14px', fontWeight: 600, fontFamily: 'monospace' }}>
                                        {String(val)}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>

            {/* Test Job Dispatch Modal */}
            <ModalDialog
                isOpen={dispatchModalOpen}
                title="Dispatch Test Queue Job"
                description="Simulate a background job to verify that queue workers are consuming tasks properly."
                confirmText={dispatching ? 'Dispatching...' : 'Dispatch Job'}
                loading={dispatching}
                onConfirm={handleDispatchTest}
                onClose={() => setDispatchModalOpen(false)}
            >
                <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
                    <label style={{ fontSize: '12px', fontWeight: 600 }}>
                        Message / Payload
                        <input
                            type="text"
                            className="input"
                            style={{ width: '100%', marginTop: '4px' }}
                            value={testMessage}
                            onChange={(e) => setTestMessage(e.target.value)}
                        />
                    </label>

                    <label style={{ fontSize: '12px', fontWeight: 600 }}>
                        Execution Delay (seconds)
                        <select
                            className="select"
                            style={{ width: '100%', marginTop: '4px' }}
                            value={testDelay}
                            onChange={(e) => setTestDelay(Number(e.target.value))}
                        >
                            <option value={0}>Immediate (0s delay)</option>
                            <option value={10}>10 seconds</option>
                            <option value={30}>30 seconds</option>
                            <option value={60}>1 minute</option>
                            <option value={300}>5 minutes</option>
                        </select>
                    </label>
                </div>
            </ModalDialog>

            {/* Job Details Modal */}
            <ModalDialog
                isOpen={Boolean(selectedJob)}
                title={selectedJob ? `Job: ${selectedJob.name}` : 'Job Details'}
                confirmText="Close"
                onConfirm={() => setSelectedJob(null)}
                onClose={() => setSelectedJob(null)}
                maxWidth="680px"
            >
                {selectedJob && (
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '12px', maxHeight: '450px', overflowY: 'auto' }}>
                        <div>
                            <div style={{ fontSize: '11px', color: 'var(--text-subtle)' }}>Job Class</div>
                            <div style={{ fontSize: '13px', fontWeight: 600, fontFamily: 'monospace' }}>{selectedJob.full_name}</div>
                        </div>

                        {isFailedJob(selectedJob) && selectedJob.exception_full && (
                            <div>
                                <div style={{ fontSize: '11px', color: 'var(--danger-color, #ef4444)', fontWeight: 600, marginBottom: '4px' }}>
                                    Exception Stack Trace
                                </div>
                                <pre
                                    style={{
                                        fontSize: '11px',
                                        backgroundColor: 'var(--bg-subtle)',
                                        padding: '10px',
                                        borderRadius: '8px',
                                        overflowX: 'auto',
                                        maxHeight: '180px',
                                        whiteSpace: 'pre-wrap',
                                    }}
                                >
                                    {selectedJob.exception_full}
                                </pre>
                            </div>
                        )}

                        <div>
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '4px' }}>
                                <span style={{ fontSize: '11px', color: 'var(--text-subtle)' }}>Raw Payload</span>
                                <CopyButton
                                    text={JSON.stringify(
                                        isFailedJob(selectedJob) ? selectedJob.payload : selectedJob.raw_payload,
                                        null,
                                        2
                                    )}
                                    label="Copy JSON"
                                />
                            </div>
                            <pre
                                style={{
                                    fontSize: '11px',
                                    backgroundColor: 'var(--bg-subtle)',
                                    padding: '10px',
                                    borderRadius: '8px',
                                    overflowX: 'auto',
                                    maxHeight: '180px',
                                }}
                            >
                                {JSON.stringify(
                                    isFailedJob(selectedJob) ? selectedJob.payload : selectedJob.raw_payload,
                                    null,
                                    2
                                )}
                            </pre>
                        </div>
                    </div>
                )}
            </ModalDialog>
        </div>
    );
}
