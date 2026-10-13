import React from 'react';
import { PageTitle } from '@/shared/component/ui/page_title';
import { DataTable } from '@/shared/component/ui/data_table';
import { CopyButton } from '@/shared/component/ui/copy_button';
import { renderBadge } from '@/shared/component/ui/badge';
import { IconRefresh, IconSearch, IconX, IconArrowLeft, IconCalendar, IconRepositories, IconFilter } from '@/shared/component/ui/icons';
import { navigate, displayValue, formatDate, getMonthRange, getLastDaysRange, getTodayDateString } from '@/shared/utils/format_utils';
import { Skeleton, SkeletonFormFields, SkeletonTableRows } from '@/shared/component/ui/skeleton';
import { ModalDialog } from '@/shared/component/ui/modal_dialog';
import { useOrderLogic, OrderFilterState } from './order_logic';
import { OrderSummaryTotals } from '../../domain/model/response/order/order_summary_response';

export interface OrderPageProps {
    mode: 'resource-index' | 'resource-create' | 'resource-edit' | 'resource-show';
    id?: string | number;
}

function formatRupiah(value: number): string {
    return `Rp ${Number(value || 0).toLocaleString('id-ID')}`;
}

function OrderSummaryStrip({ summary, loading }: { summary: OrderSummaryTotals | null; loading: boolean }): React.JSX.Element {
    const breakdown = summary?.status_breakdown || {};
    const tileClass = (key: string) => `summary-tile ${key}${loading ? ' is-loading' : ''}`;

    const tiles = [
        {
            key: 'blue',
            label: 'Total Orders',
            value: Number(summary?.total_orders || 0).toLocaleString('id-ID'),
            note: loading ? '' : `${Object.keys(breakdown).length} status group`,
        },
        {
            key: 'success',
            label: 'Total Amount',
            value: formatRupiah(summary?.total_amount || 0),
            note: loading ? '' : 'Sum of filtered orders',
        },
        {
            key: 'warning',
            label: 'Average Amount',
            value: formatRupiah(summary?.average_amount || 0),
            note: loading ? '' : 'Per order',
        },
    ];

    return (
        <div className="order-summary-strip">
            {tiles.map((tile) => (
                <div key={tile.label} className={tileClass(tile.key)}>
                    <div className="summary-tile-icon" style={{ opacity: loading ? 0.4 : 1 }} />
                    <div className="summary-tile-body">
                        <div className="summary-tile-label">{tile.label}</div>
                        <div className="summary-tile-value">{loading ? '000000' : tile.value}</div>
                        <div className="summary-tile-note">{tile.note || '\u00A0'}</div>
                    </div>
                </div>
            ))}
        </div>
    );
}

function FilterSummaryChips({
    state,
    repositories,
    onRemove,
}: {
    state: OrderFilterState;
    repositories: Array<{ id: string | number; label: string }>;
    onRemove: (patch: Partial<OrderFilterState>) => void;
}): React.JSX.Element {
    const chips: Array<{ label: string; patch: Partial<OrderFilterState> }> = [];
    if (state.searchTerm.trim()) {
        chips.push({ label: `Search: "${state.searchTerm.trim()}"`, patch: { searchTerm: '' } });
    }
    // One chip per email so each address can be dropped on its own.
    if (state.email.trim()) {
        const emails = state.email
            .split(',')
            .map((e) => e.trim())
            .filter(Boolean);
        emails.forEach((address, index) => {
            chips.push({
                label: `Email: ${address}`,
                patch: { email: emails.filter((_, i) => i !== index).join(',') },
            });
        });
    }
    if (state.startDate || state.endDate) {
        chips.push({
            label: `Date: ${state.startDate || '…'} → ${state.endDate || '…'}`,
            patch: { startDate: '', endDate: '' },
        });
    }
    if (state.hourFrom.trim() || state.hourTo.trim()) {
        const from = state.hourFrom.trim();
        const to = state.hourTo.trim();
        let jam: string;
        if (from && to) {
            jam = `${from} → ${to}`;
        } else if (from) {
            jam = `from ${from}`;
        } else {
            jam = `until ${to}`;
        }
        chips.push({ label: `Jam: ${jam}`, patch: { hourFrom: '', hourTo: '' } });
    }
    if (state.status !== 'all') {
        chips.push({ label: `Status: ${state.status}`, patch: { status: 'all' } });
    }
    if (state.mode !== 'all') {
        chips.push({ label: `Mode: ${state.mode}`, patch: { mode: 'all' } });
    }
    if (state.repository !== 'all') {
        const repo = repositories.find((r) => String(r.id) === String(state.repository));
        chips.push({ label: `Repo: ${repo?.label || `#${state.repository}`}`, patch: { repository: 'all' } });
    }

    return (
        <>
            {chips.map((chip, index) => (
                <span key={`${chip.label}-${index}`} className="active-filter-chip">
                    {chip.label}
                    <button
                        type="button"
                        className="active-filter-chip-remove"
                        onClick={() => onRemove(chip.patch)}
                        title={`Remove ${chip.label}`}
                        aria-label={`Remove filter ${chip.label}`}
                    >
                        <IconX />
                    </button>
                </span>
            ))}
        </>
    );
}

function FilterDialog({
    isOpen,
    draft,
    setDraft,
    repositories,
    onApply,
    onReset,
    onClose,
}: {
    isOpen: boolean;
    draft: OrderFilterState;
    setDraft: React.Dispatch<React.SetStateAction<OrderFilterState>>;
    repositories: Array<{ id: string | number; label: string }>;
    onApply: () => void;
    onReset: () => void;
    onClose: () => void;
}): React.JSX.Element | null {
    const patch = (partial: Partial<OrderFilterState>) => setDraft((prev) => ({ ...prev, ...partial }));

    return (
        <ModalDialog
            isOpen={isOpen}
            title="Filter Orders"
            description="Set the criteria, then apply to refresh the list and summary."
            confirmText="Apply Filters"
            cancelText="Cancel"
            maxWidth="640px"
            onConfirm={onApply}
            onClose={onClose}
        >
            <div className="filter-dialog-grid">
                <div className="filter-dialog-field full">
                    <label>Search</label>
                    <input
                        type="text"
                        className="filter-dialog-input"
                        placeholder="Reference, email, notes..."
                        value={draft.searchTerm}
                        onChange={(e) => patch({ searchTerm: e.target.value })}
                    />
                </div>

                <div className="filter-dialog-field">
                    <label>Email</label>
                    <input
                        type="text"
                        className="filter-dialog-input"
                        placeholder="alice@x.com, bob@y.com"
                        value={draft.email}
                        onChange={(e) => patch({ email: e.target.value })}
                    />
                    <span className="filter-dialog-hint">Separate multiple emails with commas</span>
                </div>

                <div className="filter-dialog-field">
                    <label>Status</label>
                    <select
                        className="filter-dialog-input"
                        value={draft.status}
                        onChange={(e) => patch({ status: e.target.value })}
                    >
                        <option value="all">All Statuses</option>
                        <option value="SUCCESS">SUCCESS</option>
                        <option value="PENDING">PENDING</option>
                        <option value="FAILED">FAILED</option>
                        <option value="EXPIRED">EXPIRED</option>
                    </select>
                </div>

                <div className="filter-dialog-field">
                    <label>From Date</label>
                    <input
                        type="date"
                        className="filter-dialog-input"
                        value={draft.startDate}
                        onChange={(e) => patch({ startDate: e.target.value })}
                    />
                </div>

                <div className="filter-dialog-field">
                    <label>To Date</label>
                    <input
                        type="date"
                        className="filter-dialog-input"
                        value={draft.endDate}
                        onChange={(e) => patch({ endDate: e.target.value })}
                    />
                </div>

                <div className="filter-dialog-field">
                    <label>Jam From</label>
                    <input
                        type="time"
                        className="filter-dialog-input"
                        value={draft.hourFrom}
                        onChange={(e) => patch({ hourFrom: e.target.value })}
                    />
                </div>

                <div className="filter-dialog-field">
                    <label>Jam To</label>
                    <input
                        type="time"
                        className="filter-dialog-input"
                        value={draft.hourTo}
                        onChange={(e) => patch({ hourTo: e.target.value })}
                    />
                </div>

                <div className="filter-dialog-field">
                    <label>Mode</label>
                    <select
                        className="filter-dialog-input"
                        value={draft.mode}
                        onChange={(e) => patch({ mode: e.target.value })}
                    >
                        <option value="all">All Modes</option>
                        <option value="prod">Production</option>
                        <option value="sandbox">Sandbox</option>
                    </select>
                </div>

                <div className="filter-dialog-field full">
                    <label>Payment Repository</label>
                    <select
                        className="filter-dialog-input"
                        value={draft.repository}
                        onChange={(e) => patch({ repository: e.target.value })}
                    >
                        <option value="all">All Repositories</option>
                        {repositories.map((repo) => (
                            <option key={repo.id} value={repo.id}>
                                {repo.label}
                            </option>
                        ))}
                    </select>
                </div>
            </div>

            <div style={{ marginTop: '16px', display: 'flex', justifyContent: 'flex-start' }}>
                <button type="button" className="button" onClick={onReset}>
                    <IconX /> Clear All
                </button>
            </div>
        </ModalDialog>
    );
}

export function OrderPage({ mode, id }: OrderPageProps): React.JSX.Element {
    const {
        records,
        record,
        loading,
        error,
        notice,
        resending,
        updatingStatus,
        confirmSuccessOrder,
        setConfirmSuccessOrder,
        confirmSetSuccessAction,
        page,
        perPage,
        total,
        currentPage,
        currentPerPage,
        repositories,
        applied,
        draft,
        setDraft,
        activeFilterCount,
        filterDialogOpen,
        openFilterDialog,
        closeFilterDialog,
        applyFilters,
        resetFilters,
        removeFilter,
        applyDatePreset,
        summary,
        summaryLoading,
        loadList,
        handlePerPageChange,
        handleResendCallback,
        handleSetSuccess,
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
                        {record?.status !== 'SUCCESS' && (
                            <button
                                type="button"
                                className="button primary"
                                disabled={updatingStatus}
                                onClick={() => id && handleSetSuccess(id, record?.reference)}
                            >
                                {updatingStatus ? 'Updating...' : 'Set Success & Send Callback'}
                            </button>
                        )}
                        <button
                            type="button"
                            className="button"
                            disabled={resending || record?.status !== 'SUCCESS'}
                            onClick={() => id && handleResendCallback(id)}
                        >
                            {resending ? 'Resending...' : 'Resend Callback'}
                        </button>
                    </div>
                </PageTitle>

                {notice && <div className="panel alert success" style={{ marginBottom: '16px' }}>{notice}</div>}
                {error && <div className="panel alert danger" style={{ marginBottom: '16px' }}>{error}</div>}

                {loading ? (
                    <SkeletonFormFields count={6} />
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
                                <span className="label">Customer Name</span>
                                <div className="input" style={{ fontWeight: 500 }}>{record.name || '-'}</div>
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

                            <div className="field">
                                <span className="label">Amount</span>
                                <div className="input mono" style={{ fontWeight: 600 }}>
                                    {record.amount !== undefined && record.amount !== null
                                        ? `Rp ${Number(record.amount).toLocaleString('id-ID')}`
                                        : '-'}
                                </div>
                            </div>

                            <div className="field full">
                                <span className="label">Value (QRIS / VA / Link / Code)</span>
                                <div className="input mono" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                    <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                                        {record.value || '-'}
                                    </span>
                                    {record.value && <CopyButton text={record.value} />}
                                </div>
                            </div>

                            <div className="field full">
                                <span className="label">Return URL (Redirect Destination)</span>
                                <div className="input mono" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                    <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                                        {record.return_url || '-'}
                                    </span>
                                    {record.return_url && <CopyButton text={record.return_url} />}
                                </div>
                            </div>
                        </div>

                        <div className="panel" style={{ padding: '24px', marginBottom: '24px' }}>
                            <div className="panel-title" style={{ fontSize: '15px', marginBottom: '16px' }}>
                                Raw Payload Inspection
                            </div>

                            <div className="form-grid">
                                {['request', 'response', 'callback'].map((payloadKey) => {
                                    const payloadData = (record as any)[payloadKey];
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

                        {/* Status Change Audit History */}
                        <div className="panel" style={{ padding: '24px' }}>
                            <div className="panel-title" style={{ fontSize: '15px', marginBottom: '16px' }}>
                                Status Change History & Audit Logs
                            </div>

                            {Array.isArray(record.histories) && record.histories.length > 0 ? (
                                <div className="table-wrap">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Timestamp</th>
                                                <th>Status Transition</th>
                                                <th>Source</th>
                                                <th>Description</th>
                                                <th>Payload Snapshot</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {record.histories.map((h: any) => (
                                                <tr key={h.id}>
                                                    <td>{formatDate(h.created_at)}</td>
                                                    <td>
                                                        <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                                                            {h.from_status ? renderBadge('status', h.from_status) : <span className="muted">-</span>}
                                                            <span style={{ color: 'var(--text-subtle)' }}>&rarr;</span>
                                                            {renderBadge('status', h.to_status)}
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span className="badge blue mono">{h.source || 'SYSTEM'}</span>
                                                    </td>
                                                    <td>{h.description || '-'}</td>
                                                    <td className="mono" style={{ maxWidth: '240px', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                                                        {h.payload ? (
                                                            <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                                                                <span>{JSON.stringify(h.payload).slice(0, 40)}...</span>
                                                                <CopyButton text={JSON.stringify(h.payload, null, 2)} />
                                                            </div>
                                                        ) : (
                                                            <span className="muted">-</span>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <div className="muted" style={{ fontSize: '13px', padding: '12px 0' }}>
                                    No status changes logged yet for this order.
                                </div>
                            )}
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
            applyDatePreset(today, today);
        } else if (preset === '7days') {
            const { startDate: s, endDate: e } = getLastDaysRange(7);
            applyDatePreset(s, e);
        } else if (preset === '30days') {
            const { startDate: s, endDate: e } = getLastDaysRange(30);
            applyDatePreset(s, e);
        } else if (preset === 'month') {
            const { startDate: s, endDate: e } = getMonthRange();
            applyDatePreset(s, e);
        }
    };

    const isMonthActive = (() => {
        const { startDate: s, endDate: e } = getMonthRange();
        return applied.startDate === s && applied.endDate === e;
    })();

    const isTodayActive = (() => {
        const today = getTodayDateString();
        return applied.startDate === today && applied.endDate === today;
    })();

    const is7DaysActive = (() => {
        const { startDate: s, endDate: e } = getLastDaysRange(7);
        return applied.startDate === s && applied.endDate === e;
    })();

    const is30DaysActive = (() => {
        const { startDate: s, endDate: e } = getLastDaysRange(30);
        return applied.startDate === s && applied.endDate === e;
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

            <OrderSummaryStrip summary={summary} loading={summaryLoading} />

            <div className="modern-filter-panel">
                <div className="modern-filter-row">
                    <button
                        type="button"
                        className="button primary"
                        onClick={openFilterDialog}
                        style={{ gap: '8px' }}
                    >
                        <IconFilter /> Filters
                        {activeFilterCount > 0 && (
                            <span className="filter-count-badge">{activeFilterCount}</span>
                        )}
                    </button>

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
                        {activeFilterCount > 0 && (
                            <button
                                type="button"
                                className="filter-chip"
                                onClick={resetFilters}
                                style={{ background: 'var(--danger-light)', color: 'var(--danger)', borderColor: 'var(--danger)' }}
                                title="Reset all filters"
                            >
                                <IconX /> Reset
                            </button>
                        )}
                    </div>

                    <select
                        className="filter-select"
                        value={perPage}
                        onChange={(e) => handlePerPageChange(Number(e.target.value))}
                    >
                        <option value="10">10 / page</option>
                        <option value="25">25 / page</option>
                        <option value="50">50 / page</option>
                        <option value="100">100 / page</option>
                    </select>
                </div>

                {activeFilterCount > 0 && (
                    <div className="active-filter-summary">
                        <span className="muted" style={{ fontSize: '12px' }}>Showing results for:</span>
                        <FilterSummaryChips
                            state={applied}
                            repositories={repositories}
                            onRemove={removeFilter}
                        />
                    </div>
                )}
            </div>

            <FilterDialog
                isOpen={filterDialogOpen}
                draft={draft}
                setDraft={setDraft}
                repositories={repositories}
                onApply={applyFilters}
                onReset={resetFilters}
                onClose={closeFilterDialog}
            />

            <div className="panel">
                <DataTable columns={['Reference', 'Customer', 'Project', 'Method', 'Amount', 'Status', 'Mode', 'Created', 'Actions']}>
                    {loading ? (
                        <SkeletonTableRows rows={perPage > 15 ? 10 : 6} columns={9} />
                    ) : records.length > 0 ? (
                        records.map((row: any) => {
                            const primaryKey = row.id || row.reference;
                            return (
                                <tr key={primaryKey}>
                                    <td className="mono">
                                        <a
                                            className="table-link"
                                            href={`/admin/orders/${primaryKey}`}
                                            onClick={(e) => {
                                                if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;
                                                e.preventDefault();
                                                navigate(`/admin/orders/${primaryKey}`);
                                            }}
                                        >
                                            {row.reference}
                                        </a>
                                        <CopyButton text={row.reference} />
                                    </td>
                                    <td>{row.name || '-'}</td>
                                    <td>{row.type || '-'}</td>
                                    <td>{row.payment_method || '-'}</td>
                                    <td className="mono" style={{ fontWeight: 600 }}>
                                        {row.amount !== undefined && row.amount !== null
                                            ? `Rp ${Number(row.amount).toLocaleString('id-ID')}`
                                            : '-'}
                                    </td>
                                    <td>{renderBadge('status', row.status)}</td>
                                    <td>{renderBadge('mode', row.mode)}</td>
                                    <td>{formatDate(row.created_at)}</td>
                                    <td>
                                        <div className="actions">
                                            <a
                                                className="button"
                                                href={`/admin/orders/${primaryKey}`}
                                                onClick={(e) => { if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return; e.preventDefault(); navigate(`/admin/orders/${primaryKey}`); }}
                                            >
                                                View
                                            </a>
                                            {row.status !== 'SUCCESS' ? (
                                                <button
                                                    className="button primary"
                                                    disabled={updatingStatus}
                                                    onClick={() => handleSetSuccess(primaryKey, row.reference)}
                                                    title="Mark status as SUCCESS and send callback webhook"
                                                >
                                                    Set Success
                                                </button>
                                            ) : (
                                                <button
                                                    className="button"
                                                    disabled={resending}
                                                    onClick={() => handleResendCallback(primaryKey)}
                                                    title="Resend callback webhook"
                                                >
                                                    Resend
                                                </button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            );
                        })
                    ) : (
                        <tr>
                            <td colSpan={8} className="empty" style={{ padding: '36px', textAlign: 'center' }}>
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

            {/* Confirm Set Success Dialog */}
            <ModalDialog
                isOpen={Boolean(confirmSuccessOrder)}
                title="Mark Order as SUCCESS?"
                description="This action will immediately update the transaction status to SUCCESS and dispatch a webhook callback to the merchant backend."
                confirmText="Yes, Set Success & Send Callback"
                cancelText="Cancel"
                confirmTone="primary"
                loading={updatingStatus}
                onConfirm={confirmSetSuccessAction}
                onClose={() => setConfirmSuccessOrder(null)}
            >
                {confirmSuccessOrder && (
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
                        <div>
                            <span className="muted">Order Reference: </span>
                            <strong className="mono">{confirmSuccessOrder.reference || confirmSuccessOrder.id}</strong>
                        </div>
                        <div className="alert warning" style={{ fontSize: '12px', margin: '8px 0 0' }}>
                            Make sure payment has been verified before triggering merchant fulfillment.
                        </div>
                    </div>
                )}
            </ModalDialog>
        </>
    );
}
