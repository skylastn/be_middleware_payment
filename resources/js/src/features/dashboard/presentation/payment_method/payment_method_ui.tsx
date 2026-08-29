import React from 'react';
import { PageTitle } from '@/shared/component/ui/page_title';
import { DataTable } from '@/shared/component/ui/data_table';
import { CopyButton } from '@/shared/component/ui/copy_button';
import { IconPlus, IconRefresh, IconSearch, IconX, IconArrowLeft } from '@/shared/component/ui/icons';
import { navigate } from '@/shared/utils/format_utils';
import { SkeletonFormFields, SkeletonTableRows } from '@/shared/component/ui/skeleton';
import { usePaymentMethodLogic } from './payment_method_logic';

export interface PaymentMethodPageProps {
    mode: 'resource-index' | 'resource-create' | 'resource-edit' | 'resource-show';
    id?: string | number;
}

export function PaymentMethodPage({ mode, id }: PaymentMethodPageProps): React.JSX.Element {
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
    } = usePaymentMethodLogic({ mode, id });

    if (mode === 'resource-create' || mode === 'resource-edit') {
        return (
            <>
                <PageTitle
                    eyebrow="Channel Configurations"
                    title={isEdit ? `Edit Method: ${form.name || id}` : 'Create Payment Method'}
                    subtitle="Configure payment channels, bank codes, and display categories."
                >
                    <div className="toolbar">
                        <button type="button" className="button" onClick={() => navigate('/admin/payment-methods')}>
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
                            <span className="label">Method Key *</span>
                            <input
                                className="input"
                                type="text"
                                required
                                placeholder="e.g. VA_BCA, QRIS, CC"
                                value={form.key}
                                onChange={(e) => setForm({ ...form, key: e.target.value })}
                            />
                        </label>

                        <label className="field">
                            <span className="label">Method Name *</span>
                            <input
                                className="input"
                                type="text"
                                required
                                placeholder="e.g. BCA Virtual Account"
                                value={form.name}
                                onChange={(e) => setForm({ ...form, name: e.target.value })}
                            />
                        </label>

                        <label className="field">
                            <span className="label">Channel Type *</span>
                            <input
                                className="input"
                                type="text"
                                required
                                placeholder="e.g. VA, EWALLET, CARD"
                                value={form.type}
                                onChange={(e) => setForm({ ...form, type: e.target.value })}
                            />
                        </label>

                        <label className="field">
                            <span className="label">Provider / From *</span>
                            <input
                                className="input"
                                type="text"
                                required
                                placeholder="e.g. duitku, xendit, midtrans, stripe"
                                value={form.from}
                                onChange={(e) => setForm({ ...form, from: e.target.value })}
                            />
                        </label>

                        <label className="field">
                            <span className="label">Bank Code</span>
                            <input
                                className="input"
                                type="text"
                                placeholder="e.g. BCA, BNI, BRI, MANDIRI"
                                value={form.bankCode}
                                onChange={(e) => setForm({ ...form, bankCode: e.target.value })}
                            />
                        </label>

                        <label className="field">
                            <span className="label">Internal Value / Channel Code</span>
                            <input
                                className="input"
                                type="text"
                                placeholder="e.g. BC, M2, OV"
                                value={form.value}
                                onChange={(e) => setForm({ ...form, value: e.target.value })}
                            />
                        </label>

                        <div className="field full" style={{ display: 'flex', gap: '12px', marginTop: '12px' }}>
                            <button className="button primary" type="submit" disabled={saving}>
                                {saving ? 'Saving...' : isEdit ? 'Update Method' : 'Create Method'}
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
                    eyebrow="Method Details"
                    title={record?.name || `Method #${id}`}
                    subtitle="Payment channel properties, bank codes, and internal mapping."
                >
                    <div className="toolbar">
                        <button type="button" className="button" onClick={() => navigate('/admin/payment-methods')}>
                            <IconArrowLeft /> Back to Methods
                        </button>
                        <button type="button" className="button primary" onClick={() => navigate(`/admin/payment-methods/${id}/edit`)}>
                            Edit Method
                        </button>
                    </div>
                </PageTitle>

                {error && <div className="panel alert danger" style={{ marginBottom: '16px' }}>{error}</div>}

                {loading ? (
                    <SkeletonFormFields count={6} />
                ) : record ? (
                    <div className="panel form-grid">
                        <div className="field">
                            <span className="label">Method ID</span>
                            <div className="input mono" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                <span>{record.id}</span>
                                <CopyButton text={String(record.id)} />
                            </div>
                        </div>

                        <div className="field">
                            <span className="label">Method Key</span>
                            <div><span className="badge blue">{record.key}</span></div>
                        </div>

                        <div className="field">
                            <span className="label">Method Name</span>
                            <div className="input">{record.name}</div>
                        </div>

                        <div className="field">
                            <span className="label">Type</span>
                            <div><span className="badge">{record.type || '-'}</span></div>
                        </div>

                        <div className="field">
                            <span className="label">Provider / From</span>
                            <div><span className="badge success">{record.from || '-'}</span></div>
                        </div>

                        <div className="field">
                            <span className="label">Bank Code</span>
                            <div className="input mono">{record.bankCode || '-'}</div>
                        </div>

                        <div className="field full">
                            <span className="label">Channel Value</span>
                            <div className="input mono">{record.value || '-'}</div>
                        </div>
                    </div>
                ) : (
                    <div className="panel empty">Payment method not found.</div>
                )}
            </>
        );
    }

    return (
        <>
            <PageTitle
                eyebrow="Channel Configurations"
                title="Payment Methods"
                subtitle="Manage available payment methods, banks, e-wallets, and provider channel keys."
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
                        onClick={() => navigate('/admin/payment-methods/create')}
                    >
                        <IconPlus /> Create Method
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
                            placeholder="Search payment methods (key, name, from, bank)..."
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
                            <option value="100">100 / page</option>
                        </select>
                    </div>
                </div>

                <DataTable columns={['Key', 'Name', 'Type', 'From', 'Bank Code', 'Value', 'Actions']}>
                    {loading ? (
                        <SkeletonTableRows rows={perPage > 15 ? 10 : 6} columns={7} />
                    ) : records.length > 0 ? (
                        records.map((row: any) => {
                            const primaryKey = row.id || row.key;
                            return (
                                <tr key={primaryKey}>
                                    <td className="mono">
                                        <span className="badge blue">{row.key}</span>
                                        <div className="muted mono" style={{ fontSize: '11px' }}>
                                            ID: {row.id}
                                            <CopyButton text={String(row.id)} />
                                        </div>
                                    </td>
                                    <td><strong>{row.name}</strong></td>
                                    <td><span className="badge">{row.type || '-'}</span></td>
                                    <td><span className="badge success">{row.from || '-'}</span></td>
                                    <td>{row.bankCode || '-'}</td>
                                    <td className="mono">{row.value || '-'}</td>
                                    <td>
                                        <div className="actions">
                                            <button
                                                className="button"
                                                onClick={() => navigate(`/admin/payment-methods/${primaryKey}`)}
                                            >
                                                View
                                            </button>
                                            <button
                                                className="button"
                                                onClick={() => navigate(`/admin/payment-methods/${primaryKey}/edit`)}
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
                            <td colSpan={7} className="empty" style={{ padding: '36px', textAlign: 'center' }}>
                                No payment methods found.
                            </td>
                        </tr>
                    )}
                </DataTable>
            </div>

            {total > perPage && (
                <div className="pagination">
                    <div>
                        Showing page {currentPage} of {Math.ceil(total / perPage)} ({total} total methods)
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
