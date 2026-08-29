import React from 'react';
import { PageTitle } from '@/shared/component/ui/page_title';
import { DataTable } from '@/shared/component/ui/data_table';
import { CopyButton } from '@/shared/component/ui/copy_button';
import { renderBadge } from '@/shared/component/ui/badge';
import { IconRefresh, IconSearch, IconX, IconArrowLeft, IconCalendar, IconRepositories } from '@/shared/component/ui/icons';
import { navigate, displayValue, formatDate, getMonthRange, getLastDaysRange, getTodayDateString } from '@/shared/utils/format_utils';
import { Skeleton, SkeletonTableRows } from '@/shared/component/ui/skeleton';
import { useOrderLogic } from './order_logic';

export interface OrderPageProps {
    mode: 'resource-index' | 'resource-create' | 'resource-edit' | 'resource-show';
    id?: string | number;
}

export function OrderPage({ mode, id }: OrderPageProps): React.JSX.Element {
    const {
        records,
        record,
        loading,
        error,
        notice,
        resending,
        searchTerm,
        setSearchTerm,
        page,
        perPage,
        setPerPage,
        total,
        currentPage,
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
        handleSearchSubmit,
        handleClearSearch,
        handleResendCallback,
    } = useOrderLogic({ mode, id });

    if (mode === 'resource-show') {
        return (
            <>
                <PageTitle
                    eyebrow="Order Details"
                    title={`Order #${record?.reference || id}`}
                    subtitle="Detailed transaction records, gateway responses, and callback audit payload."
                >
                    <div className="toolbar">
                        <button type="button" className="button" onClick={() => navigate('/admin/orders')}>
                            <IconArrowLeft /> Back to Orders
                        </button>
                        <button
                            type="button"
                            className="button primary"
                            disabled={resending}
                            onClick={() => id && handleResendCallback(id)}
                        >
                            {resending ? 'Resending...' : 'Resend Callback'}
                        </button>
                    </div>
                </PageTitle>

                {notice && <div className="panel alert success" style={{ marginBottom: '16px' }}>{notice}</div>}
                {error && <div className="panel alert danger" style={{ marginBottom: '16px' }}>{error}</div>}

                {loading ? (
                    <div className="panel empty">Loading order details...</div>
                ) : record ? (
                    <>
                        <div className="panel form-grid" style={{ marginBottom: '24px' }}>
                            <div className="field full">
                                <div className="panel-title" style={{ fontSize: '15px' }}>Transaction Overview</div>
                            </div>

                            <div className="field">
                                <span className="label">Order Reference</span>
                                <div className="input mono" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                    <span>{record.reference || '-'}</span>
                                    {record.reference && <CopyButton text={record.reference} />}
                                </div>
                            </div>

                            <div className="field">
                                <span className="label">Internal ID</span>
                                <div className="input mono" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                    <span>{record.id || '-'}</span>
                                    {record.id && <CopyButton text={String(record.id)} />}
                                </div>
                            </div>

                            <div className="field">
                                <span className="label">Payment Status</span>
                                <div>{renderBadge('status', record.status)}</div>
                            </div>

                            <div className="field">
                                <span className="label">Mode</span>
                                <div>{renderBadge('mode', record.mode)}</div>
                            </div>

                            <div className="field">
                                <span className="label">Project Type</span>
                                <div className="input mono">{record.type || '-'}</div>
                            </div>

                            <div className="field">
                                <span className="label">Payment Method</span>
                                <div className="input mono">{record.payment_method || '-'}</div>
                            </div>
                        </div>

                        <div className="panel" style={{ padding: '24px' }}>
                            <div className="panel-title" style={{ fontSize: '15px', marginBottom: '16px' }}>
                                Raw Payload Inspection
                            </div>

                            <div className="form-grid">
                                {['request', 'response', 'callback'].map((payloadKey) => {
                                    const payloadData = record[payloadKey];
                                    const jsonString = displayValue(payloadData);

                                    return (
                                        <div className="field full" key={payloadKey}>
                                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '4px' }}>
                                                <span className="label" style={{ textTransform: 'capitalize' }}>
                                                    {payloadKey} Payload
                                                </span>
                                                {jsonString !== '-' && jsonString !== '' && <CopyButton text={jsonString} />}
                                            </div>
                                            <pre
                                                className="mono"
                                                style={{
                                                    margin: 0,
                                                    padding: '12px',
                                                    background: 'var(--bg-page)',
                                                    border: '1px solid var(--border)',
                                                    borderRadius: 'var(--radius-md)',
                                                    whiteSpace: 'pre-wrap',
                                                    maxHeight: '260px',
                                                    overflowY: 'auto',
                                                    fontSize: '12px',
                                                }}
                                            >
                                                {jsonString || '-'}
                                            </pre>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    </>
                ) : (
                    <div className="panel empty">Order not found.</div>
                )}
            </>
        );
    }

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

    return (
        <>
            <PageTitle
                eyebrow="Transaction History"
                title="Orders Management"
                subtitle="Monitor, search, and audit transaction records across all integrated payment gateways."
            >
                <div className="toolbar">
                    <button
                        type="button"
                        className="button"
                        onClick={() => loadList(page)}
                        disabled={loading}
                        title="Refresh orders"
                    >
                        <IconRefresh /> Refresh
                    </button>
                </div>
            </PageTitle>

            {notice && <div className="panel alert success" style={{ marginBottom: '16px' }}>{notice}</div>}
            {error && <div className="panel alert danger" style={{ marginBottom: '16px' }}>{error}</div>}

            <div className="modern-filter-panel">
                <div className="modern-filter-row">
                    <form className="search-box" onSubmit={handleSearchSubmit} style={{ minWidth: '280px', flex: '1 1 300px' }}>
                        <span className="search-icon"><IconSearch /></span>
                        <input
                            className="search-input"
                            type="text"
                            placeholder="Search reference, email, notes..."
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                        />
                        {searchTerm && (
                            <button className="search-clear" type="button" onClick={handleClearSearch}>
                                <IconX />
                            </button>
                        )}
                    </form>

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
                    </div>
                </div>

                <div className="modern-filter-row" style={{ paddingTop: '8px', borderTop: '1px solid var(--border)' }}>
                    <div className="modern-filter-fields">
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

                        <div className="modern-input-group">
                            <span className="input-icon"><IconRepositories /></span>
                            <label>Repo</label>
                            <select
                                value={selectedRepository}
                                onChange={(e) => setSelectedRepository(e.target.value)}
                            >
                                <option value="all">All Repositories</option>
                                {repositories.map((repo) => (
                                    <option key={repo.id} value={repo.id}>
                                        {repo.label}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <select
                            className="filter-select"
                            value={selectedMode}
                            onChange={(e) => setSelectedMode(e.target.value)}
                        >
                            <option value="all">All Modes</option>
                            <option value="prod">Production</option>
                            <option value="sandbox">Sandbox</option>
                        </select>

                        <select
                            className="filter-select"
                            value={selectedStatus}
                            onChange={(e) => setSelectedStatus(e.target.value)}
                        >
                            <option value="all">All Statuses</option>
                            <option value="SUCCESS">SUCCESS</option>
                            <option value="PENDING">PENDING</option>
                            <option value="FAILED">FAILED</option>
                            <option value="EXPIRED">EXPIRED</option>
                        </select>

                        <select
                            className="filter-select"
                            value={perPage}
                            onChange={(e) => setPerPage(Number(e.target.value))}
                        >
                            <option value="10">10 / page</option>
                            <option value="25">25 / page</option>
                            <option value="50">50 / page</option>
                            <option value="100">100 / page</option>
                        </select>

                        {(searchTerm || startDate || endDate || selectedRepository !== 'all' || selectedMode !== 'all' || selectedStatus !== 'all') && (
                            <button
                                type="button"
                                className="filter-chip"
                                onClick={handleClearSearch}
                                style={{ background: 'var(--danger-light)', color: 'var(--danger)', borderColor: 'var(--danger)', height: '36px' }}
                                title="Reset all filters"
                            >
                                <IconX /> Reset
                            </button>
                        )}
                    </div>
                </div>
            </div>

            <div className="panel">
                <DataTable columns={['Reference', 'Project', 'Method', 'Status', 'Mode', 'Created', 'Actions']}>
                    {loading ? (
                        <SkeletonTableRows rows={perPage > 15 ? 10 : 6} columns={7} />
                    ) : records.length > 0 ? (
                        records.map((row: any) => {
                            const primaryKey = row.id || row.reference;
                            return (
                                <tr key={primaryKey}>
                                    <td className="mono">
                                        {row.reference}
                                        <CopyButton text={row.reference} />
                                    </td>
                                    <td>{row.type || '-'}</td>
                                    <td>{row.payment_method || '-'}</td>
                                    <td>{renderBadge('status', row.status)}</td>
                                    <td>{renderBadge('mode', row.mode)}</td>
                                    <td>{formatDate(row.created_at)}</td>
                                    <td>
                                        <div className="actions">
                                            <button
                                                className="button"
                                                onClick={() => navigate(`/admin/orders/${primaryKey}`)}
                                            >
                                                View
                                            </button>
                                            <button
                                                className="button"
                                                disabled={resending}
                                                onClick={() => handleResendCallback(primaryKey)}
                                            >
                                                Resend
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            );
                        })
                    ) : (
                        <tr>
                            <td colSpan={7} className="empty" style={{ padding: '36px', textAlign: 'center' }}>
                                No orders found.
                            </td>
                        </tr>
                    )}
                </DataTable>
            </div>

            {total > perPage && (
                <div className="pagination">
                    <div>
                        Showing page {currentPage} of {Math.ceil(total / perPage)} ({total} total orders)
                    </div>
                    <div className="pagination-actions">
                        <button
                            className="button"
                            disabled={currentPage <= 1 || loading}
                            onClick={() => loadList(currentPage - 1)}
                        >
                            Previous
                        </button>
                        <span className="button">Page {currentPage}</span>
                        <button
                            className="button"
                            disabled={currentPage >= Math.ceil(total / perPage) || loading}
                            onClick={() => loadList(currentPage + 1)}
                        >
                            Next
                        </button>
                    </div>
                </div>
            )}
        </>
    );
}
