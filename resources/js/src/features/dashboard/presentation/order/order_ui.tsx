import React from 'react';
import { PageTitle } from '@/shared/component/ui/page_title';
import { DataTable } from '@/shared/component/ui/data_table';
import { CopyButton } from '@/shared/component/ui/copy_button';
import { renderBadge } from '@/shared/component/ui/badge';
import { IconRefresh, IconSearch, IconX, IconArrowLeft } from '@/shared/component/ui/icons';
import { navigate, displayValue, formatDate } from '@/shared/utils/format_utils';
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

            <div className="panel">
                <div className="filter-bar">
                    <form className="search-box" onSubmit={handleSearchSubmit}>
                        <span className="search-icon"><IconSearch /></span>
                        <input
                            className="search-input"
                            type="text"
                            placeholder="Search orders (reference, email, notes)..."
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
                            <option value="15">15 / page</option>
                            <option value="25">25 / page</option>
                            <option value="50">50 / page</option>
                            <option value="100">100 / page</option>
                        </select>
                    </div>
                </div>

                <DataTable columns={['Reference', 'Project', 'Method', 'Status', 'Mode', 'Created', 'Actions']}>
                    {loading ? (
                        <tr>
                            <td colSpan={7} className="empty" style={{ padding: '36px', textAlign: 'center' }}>
                                Loading order records...
                            </td>
                        </tr>
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
