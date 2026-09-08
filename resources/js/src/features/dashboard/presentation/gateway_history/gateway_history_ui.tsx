import React from 'react';
import { PageTitle } from '@/shared/component/ui/page_title';
import { DataTable } from '@/shared/component/ui/data_table';
import { CopyButton } from '@/shared/component/ui/copy_button';
import { IconCalendar, IconRefresh, IconRepositories, IconX } from '@/shared/component/ui/icons';
import { formatDate, getMonthRange, getLastDaysRange, getTodayDateString, displayValue } from '@/shared/utils/format_utils';
import { SkeletonTableRows } from '@/shared/component/ui/skeleton';
import { useGatewayHistoryLogic } from './gateway_history_logic';

export function GatewayHistoryPage(): React.JSX.Element {
    const {
        payload,
        loading,
        error,
        repositories,
        selectedRepository,
        setSelectedRepository,
        selectedItem,
        setSelectedItem,
        startDate,
        setStartDate,
        endDate,
        setEndDate,
        perPage,
        setPerPage,
        handleClearFilters,
        reload,
    } = useGatewayHistoryLogic();

    const applyPreset = (preset: 'today' | '7days' | 'month' | '30days') => {
        if (preset === 'today') {
            const today = getTodayDateString();
            setStartDate(today);
            setEndDate(today);
        } else if (preset === '7days') {
            const { startDate: s, endDate: e } = getLastDaysRange(7);
            setStartDate(s);
            setEndDate(e);
        } else if (preset === '30days') {
            const { startDate: s, endDate: e } = getLastDaysRange(30);
            setStartDate(s);
            setEndDate(e);
        } else if (preset === 'month') {
            const { startDate: s, endDate: e } = getMonthRange();
            setStartDate(s);
            setEndDate(e);
        }
    };

    const isMonthActive = (() => {
        const { startDate: s, endDate: e } = getMonthRange();
        return startDate === s && endDate === e;
    })();

    const isTodayActive = (() => {
        const today = getTodayDateString();
        return startDate === today && endDate === today;
    })();

    const is7DaysActive = (() => {
        const { startDate: s, endDate: e } = getLastDaysRange(7);
        return startDate === s && endDate === e;
    })();

    const is30DaysActive = (() => {
        const { startDate: s, endDate: e } = getLastDaysRange(30);
        return startDate === s && endDate === e;
    })();

    const items = payload?.items || [];
    const repoInfo = payload?.repository;

    return (
        <>
            <PageTitle
                eyebrow="Third-Party Inquiries"
                title="Gateway Live History"
                subtitle="Query real-time transaction history and settlement records directly from connected payment gateways."
            >
                <div className="toolbar">
                    <button
                        type="button"
                        className="button"
                        onClick={reload}
                        disabled={loading || !selectedRepository}
                        title="Fetch latest from gateway"
                    >
                        <IconRefresh /> Refresh from Gateway
                    </button>
                </div>
            </PageTitle>

            <div className="modern-filter-panel">
                <div className="modern-filter-row">
                    <div className="modern-filter-fields">
                        <div className="modern-input-group" style={{ minWidth: '280px' }}>
                            <span className="input-icon"><IconRepositories /></span>
                            <label>Repository *</label>
                            <select
                                value={selectedRepository}
                                onChange={(e) => setSelectedRepository(e.target.value)}
                            >
                                {repositories.length === 0 && (
                                    <option value="">Loading repositories...</option>
                                )}
                                {repositories.map((repo) => (
                                    <option key={repo.id} value={repo.id}>
                                        {repo.label}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="modern-input-group">
                            <span className="input-icon"><IconCalendar /></span>
                            <label>From</label>
                            <input
                                type="date"
                                value={startDate}
                                onChange={(e) => setStartDate(e.target.value)}
                            />
                        </div>

                        <div className="modern-input-group">
                            <span className="input-icon"><IconCalendar /></span>
                            <label>To</label>
                            <input
                                type="date"
                                value={endDate}
                                onChange={(e) => setEndDate(e.target.value)}
                            />
                        </div>

                        <select
                            className="filter-select"
                            value={perPage}
                            onChange={(e) => setPerPage(Number(e.target.value))}
                        >
                            <option value="10">10 / page</option>
                            <option value="25">25 / page</option>
                            <option value="50">50 / page</option>
                        </select>
                    </div>

                    <div className="modern-filter-chips">
                        <span
                            className={`filter-chip ${isTodayActive ? 'active' : ''}`}
                            onClick={() => applyPreset('today')}
                        >
                            Today
                        </span>
                        <span
                            className={`filter-chip ${is7DaysActive ? 'active' : ''}`}
                            onClick={() => applyPreset('7days')}
                        >
                            Last 7 Days
                        </span>
                        <span
                            className={`filter-chip ${isMonthActive ? 'active' : ''}`}
                            onClick={() => applyPreset('month')}
                        >
                            This Month
                        </span>
                        <span
                            className={`filter-chip ${is30DaysActive ? 'active' : ''}`}
                            onClick={() => applyPreset('30days')}
                        >
                            Last 30 Days
                        </span>
                        {(startDate || endDate) && (
                            <button
                                type="button"
                                className="filter-chip"
                                onClick={handleClearFilters}
                                style={{ background: 'var(--danger-light)', color: 'var(--danger)', borderColor: 'var(--danger)', height: '36px' }}
                                title="Clear dates"
                            >
                                <IconX /> Reset
                            </button>
                        )}
                    </div>
                </div>
            </div>

            {error && <div className="panel alert danger" style={{ marginBottom: '16px' }}>{error}</div>}
            {payload?.message && <div className="panel alert info" style={{ marginBottom: '16px' }}>{payload.message}</div>}

            <div className="panel">
                <DataTable columns={['Gateway Ref / ID', 'Amount', 'Status', 'Method', 'Customer', 'Created', 'Actions']}>
                    {loading ? (
                        <SkeletonTableRows rows={perPage} columns={7} />
                    ) : items.length > 0 ? (
                        items.map((row) => (
                            <tr key={row.id}>
                                <td className="mono">
                                    <div>{row.reference || row.id}</div>
                                    <div className="muted" style={{ fontSize: '11px' }}>
                                        ID: {row.id}
                                        <CopyButton text={row.id} />
                                    </div>
                                </td>
                                <td>
                                    <strong>{row.currency} {row.amount.toLocaleString()}</strong>
                                </td>
                                <td>
                                    <span className={`badge ${row.status.includes('SUCCEED') || row.status.includes('SUCCESS') || row.status === 'PAID' || row.status === 'SETTLEMENT' ? 'success' : row.status === 'PENDING' ? 'warning' : 'blue'}`}>
                                        {row.status}
                                    </span>
                                </td>
                                <td>{row.payment_method || '-'}</td>
                                <td>{row.customer || '-'}</td>
                                <td>{formatDate(row.created_at)}</td>
                                <td>
                                    <div className="actions">
                                        {row.raw && (
                                            <button
                                                className="button"
                                                onClick={() => setSelectedItem(row)}
                                            >
                                                Inspect Raw
                                            </button>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))
                    ) : (
                        <tr>
                            <td colSpan={7} className="empty" style={{ padding: '36px', textAlign: 'center' }}>
                                No live transactions returned from {repoInfo?.gateway || 'gateway'}.
                            </td>
                        </tr>
                    )}
                </DataTable>
            </div>

            {/* Inspect Raw Modal / Panel */}
            {selectedItem && (
                <div className="sidebar-backdrop" style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 1000 }} onClick={() => setSelectedItem(null)}>
                    <div
                        className="panel"
                        style={{ width: '90%', maxWidth: '720px', maxHeight: '85vh', display: 'flex', flexDirection: 'column', padding: '24px', background: 'var(--surface)' }}
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px' }}>
                            <div className="panel-title" style={{ fontSize: '16px' }}>
                                Raw Gateway Response ({selectedItem.id})
                            </div>
                            <button
                                type="button"
                                className="button ghost-btn"
                                onClick={() => setSelectedItem(null)}
                            >
                                <IconX />
                            </button>
                        </div>
                        <div style={{ flex: 1, overflowY: 'auto' }}>
                            <pre
                                className="mono"
                                style={{
                                    margin: 0,
                                    padding: '16px',
                                    background: 'var(--bg-page)',
                                    border: '1px solid var(--border)',
                                    borderRadius: 'var(--radius-md)',
                                    whiteSpace: 'pre-wrap',
                                    fontSize: '12px',
                                }}
                            >
                                {displayValue(selectedItem.raw)}
                            </pre>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
