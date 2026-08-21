import React from 'react';
import { PageTitle } from '@/shared/component/ui/page_title';
import { DataTable } from '@/shared/component/ui/data_table';
import { CopyButton } from '@/shared/component/ui/copy_button';
import { IconPlus, IconRefresh, IconSearch, IconX, IconArrowLeft } from '@/shared/component/ui/icons';
import { navigate, formatDate } from '@/shared/utils/format_utils';
import { usePaymentGatewayLogic } from './payment_gateway_logic';

export interface PaymentGatewayPageProps {
    mode: 'resource-index' | 'resource-create' | 'resource-edit' | 'resource-show';
    id?: string | number;
}

export function PaymentGatewayPage({ mode, id }: PaymentGatewayPageProps): React.JSX.Element {
    const {
        isEdit,
        records,
        record,
        form,
        setForm,
        loading,
        saving,
        error,
        searchTerm,
        setSearchTerm,
        page,
        perPage,
        setPerPage,
        total,
        currentPage,
        loadList,
        handleSearchSubmit,
        handleClearSearch,
        handleFormSubmit,
        handleDelete,
    } = usePaymentGatewayLogic({ mode, id });

    if (mode === 'resource-create' || mode === 'resource-edit') {
        return (
            <>
                <PageTitle
                    eyebrow="Payment Providers"
                    title={isEdit ? `Edit Gateway: ${form.name || id}` : 'Create Payment Gateway'}
                    subtitle="Register or modify payment gateway provider entities."
                >
                    <div className="toolbar">
                        <button type="button" className="button" onClick={() => navigate('/admin/payment-gateways')}>
                            <IconArrowLeft /> Cancel
                        </button>
                    </div>
                </PageTitle>

                {error && <div className="panel alert danger" style={{ marginBottom: '16px' }}>{error}</div>}

                <div className="panel">
                    <form className="form-grid" onSubmit={handleFormSubmit}>
                        {isEdit && id && (
                            <label className="field full">
                                <span className="label">ID (Primary Key)</span>
                                <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                    <input className="input" type="text" value={String(id)} disabled readOnly />
                                    <CopyButton text={String(id)} />
                                </div>
                            </label>
                        )}

                        <label className="field">
                            <span className="label">Gateway Key *</span>
                            <input
                                className="input"
                                type="text"
                                required
                                placeholder="e.g. duitku, midtrans, stripe"
                                value={form.key}
                                onChange={(e) => setForm({ ...form, key: e.target.value })}
                            />
                        </label>

                        <label className="field">
                            <span className="label">Gateway Display Name *</span>
                            <input
                                className="input"
                                type="text"
                                required
                                placeholder="e.g. Duitku Payment, Stripe Global"
                                value={form.name}
                                onChange={(e) => setForm({ ...form, name: e.target.value })}
                            />
                        </label>

                        <label className="field full">
                            <span className="label">Description *</span>
                            <textarea
                                className="input"
                                required
                                placeholder="Provider description and documentation details"
                                value={form.description}
                                onChange={(e) => setForm({ ...form, description: e.target.value })}
                            />
                        </label>

                        <div className="field full" style={{ display: 'flex', gap: '12px', marginTop: '12px' }}>
                            <button className="button primary" type="submit" disabled={saving}>
                                {saving ? 'Saving...' : isEdit ? 'Update Gateway' : 'Create Gateway'}
                            </button>
                        </div>
                    </form>
                </div>
            </>
        );
    }

    if (mode === 'resource-show') {
        return (
            <>
                <PageTitle
                    eyebrow="Gateway Details"
                    title={record?.name || `Gateway #${id}`}
                    subtitle="Gateway provider metadata, keys, and descriptions."
                >
                    <div className="toolbar">
                        <button type="button" className="button" onClick={() => navigate('/admin/payment-gateways')}>
                            <IconArrowLeft /> Back to Gateways
                        </button>
                        <button type="button" className="button primary" onClick={() => navigate(`/admin/payment-gateways/${id}/edit`)}>
                            Edit Gateway
                        </button>
                    </div>
                </PageTitle>

                {error && <div className="panel alert danger" style={{ marginBottom: '16px' }}>{error}</div>}

                {loading ? (
                    <div className="panel empty">Loading gateway details...</div>
                ) : record ? (
                    <div className="panel form-grid">
                        <div className="field">
                            <span className="label">Gateway ID</span>
                            <div className="input mono" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                <span>{record.id}</span>
                                <CopyButton text={String(record.id)} />
                            </div>
                        </div>

                        <div className="field">
                            <span className="label">Key</span>
                            <div><span className="badge blue">{record.key}</span></div>
                        </div>

                        <div className="field">
                            <span className="label">Display Name</span>
                            <div className="input">{record.name}</div>
                        </div>

                        <div className="field full">
                            <span className="label">Description</span>
                            <div className="input">{record.description || '-'}</div>
                        </div>

                        <div className="field">
                            <span className="label">Created At</span>
                            <div className="input">{formatDate(record.created_at)}</div>
                        </div>
                    </div>
                ) : (
                    <div className="panel empty">Payment gateway not found.</div>
                )}
            </>
        );
    }

    return (
        <>
            <PageTitle
                eyebrow="Payment Providers"
                title="Payment Gateways"
                subtitle="Manage supported payment gateway drivers and provider definitions."
            >
                <div className="toolbar">
                    <button
                        type="button"
                        className="button"
                        onClick={() => loadList(page)}
                        disabled={loading}
                    >
                        <IconRefresh /> Refresh
                    </button>
                    <button
                        type="button"
                        className="button primary"
                        onClick={() => navigate('/admin/payment-gateways/create')}
                    >
                        <IconPlus /> Create Gateway
                    </button>
                </div>
            </PageTitle>

            {error && <div className="panel alert danger" style={{ marginBottom: '16px' }}>{error}</div>}

            <div className="panel">
                <div className="filter-bar">
                    <form className="search-box" onSubmit={handleSearchSubmit}>
                        <span className="search-icon"><IconSearch /></span>
                        <input
                            className="search-input"
                            type="text"
                            placeholder="Search payment gateways (key, name)..."
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
                            value={perPage}
                            onChange={(e) => setPerPage(Number(e.target.value))}
                        >
                            <option value="15">15 / page</option>
                            <option value="25">25 / page</option>
                            <option value="50">50 / page</option>
                        </select>
                    </div>
                </div>

                <DataTable columns={['Key', 'Name', 'Description', 'Created', 'Actions']}>
                    {loading ? (
                        <tr>
                            <td colSpan={5} className="empty" style={{ padding: '36px', textAlign: 'center' }}>
                                Loading payment gateways...
                            </td>
                        </tr>
                    ) : records.length > 0 ? (
                        records.map((row: any) => {
                            const primaryKey = row.id || row.key;
                            return (
                                <tr key={primaryKey}>
                                    <td>
                                        <span className="badge blue">{row.key}</span>
                                        <div className="muted mono" style={{ fontSize: '11px' }}>
                                            ID: {row.id}
                                            <CopyButton text={String(row.id)} />
                                        </div>
                                    </td>
                                    <td><strong>{row.name}</strong></td>
                                    <td style={{ maxWidth: '300px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                                        {row.description}
                                    </td>
                                    <td>{formatDate(row.created_at)}</td>
                                    <td>
                                        <div className="actions">
                                            <button
                                                className="button"
                                                onClick={() => navigate(`/admin/payment-gateways/${primaryKey}`)}
                                            >
                                                View
                                            </button>
                                            <button
                                                className="button"
                                                onClick={() => navigate(`/admin/payment-gateways/${primaryKey}/edit`)}
                                            >
                                                Edit
                                            </button>
                                            <button
                                                className="button danger"
                                                onClick={() => handleDelete(primaryKey)}
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            );
                        })
                    ) : (
                        <tr>
                            <td colSpan={5} className="empty" style={{ padding: '36px', textAlign: 'center' }}>
                                No payment gateways found.
                            </td>
                        </tr>
                    )}
                </DataTable>
            </div>

            {total > perPage && (
                <div className="pagination">
                    <div>
                        Showing page {currentPage} of {Math.ceil(total / perPage)} ({total} total gateways)
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
