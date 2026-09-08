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
        categories,
        gateways,
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
        handleSearchSubmit,
        handleClearSearch,
        handleImageUpload,
        handleClearImage,
        handleFormSubmit,
        handleDelete,
        loadList,
    } = usePaymentMethodLogic({ mode, id });

    if (mode === 'resource-create' || mode === 'resource-edit') {
        const formPreviewUrl = form.image_url || (form.image && (form.image.startsWith('http') || form.image.startsWith('data:')) ? form.image : (form.image ? `/storage/${form.image.replace(/^storage\//, '')}` : ''));

        return (
            <>
                <PageTitle
                    eyebrow="Channel Definition"
                    title={isEdit ? `Edit Method #${id}` : 'Create Payment Method'}
                    subtitle="Configure payment channel keys, provider drivers, image logos, and bank routing identifiers."
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
                        <label className="field">
                            <span className="label">Method Key / Code *</span>
                            <input
                                className="input"
                                type="text"
                                required
                                placeholder="e.g. DQ, SP, BC, BR, VC"
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
                                placeholder="e.g. Dana QRIS, BCA Virtual Account"
                                value={form.name}
                                onChange={(e) => setForm({ ...form, name: e.target.value })}
                            />
                        </label>

                        <label className="field">
                            <span className="label">Payment Category *</span>
                            <select
                                className="input"
                                required
                                value={form.category_id || ''}
                                onChange={(e) => setForm({ ...form, category_id: e.target.value ? Number(e.target.value) : '' })}
                            >
                                <option value="">-- Select Category --</option>
                                {categories.map((cat) => (
                                    <option key={cat.id} value={cat.id}>
                                        {cat.title} ({cat.key})
                                    </option>
                                ))}
                            </select>
                        </label>

                        <label className="field">
                            <span className="label">Gateway Provider (From) *</span>
                            <select
                                className="input"
                                required
                                value={form.from || ''}
                                onChange={(e) => setForm({ ...form, from: e.target.value })}
                            >
                                <option value="">-- Select Gateway Provider --</option>
                                {gateways.length > 0 ? (
                                    gateways.map((gw) => (
                                        <option key={gw.id || gw.key} value={gw.key}>
                                            {gw.name} ({gw.key})
                                        </option>
                                    ))
                                ) : (
                                    <>
                                        <option value="duitku">Duitku (duitku)</option>
                                        <option value="midtrans">Midtrans (midtrans)</option>
                                        <option value="xendit">Xendit (xendit)</option>
                                        <option value="spnpay">SPNPay (spnpay)</option>
                                        <option value="stripe">Stripe (stripe)</option>
                                        <option value="paprika">Paprika (paprika)</option>
                                    </>
                                )}
                            </select>
                        </label>

                        <label className="field full">
                            <span className="label">Bank Code / Sub-code</span>
                            <input
                                className="input mono"
                                type="text"
                                placeholder="e.g. bca, bni, mandiri, permata"
                                value={form.bankCode}
                                onChange={(e) => setForm({ ...form, bankCode: e.target.value })}
                            />
                        </label>

                        <div className="field full">
                            <span className="label">Channel Logo / Image (Optional)</span>
                            <div style={{ display: 'flex', gap: '16px', alignItems: 'flex-start' }}>
                                <div style={{
                                    width: '72px',
                                    height: '72px',
                                    borderRadius: '8px',
                                    border: '1px solid var(--border)',
                                    background: '#ffffff',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    overflow: 'hidden',
                                    flexShrink: 0,
                                }}>
                                    {formPreviewUrl ? (
                                        <img
                                            src={formPreviewUrl}
                                            alt="Preview"
                                            style={{ width: '100%', height: '100%', objectFit: 'contain', padding: '4px' }}
                                        />
                                    ) : (
                                        <span className="muted" style={{ fontSize: '11px', textAlign: 'center' }}>No Image</span>
                                    )}
                                </div>
                                <div style={{ flex: 1, display: 'flex', flexDirection: 'column', gap: '8px' }}>
                                    <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
                                        <label className="button" style={{ cursor: 'pointer', margin: 0 }}>
                                            <span>{form.image ? 'Change Image' : 'Upload Image'}</span>
                                            <input
                                                type="file"
                                                accept="image/*"
                                                style={{ display: 'none' }}
                                                onChange={(e) => {
                                                    const file = e.target.files?.[0];
                                                    if (file) handleImageUpload(file);
                                                }}
                                            />
                                        </label>
                                        {form.image && (
                                            <button
                                                type="button"
                                                className="button danger"
                                                onClick={handleClearImage}
                                            >
                                                Remove Image
                                            </button>
                                        )}
                                    </div>
                                    <input
                                        className="input mono"
                                        type="text"
                                        placeholder="Or enter path / image URL (e.g. payment-methods/logo.png or https://...)"
                                        value={form.image || ''}
                                        onChange={(e) => setForm({ ...form, image: e.target.value, image_url: e.target.value })}
                                    />
                                </div>
                            </div>
                        </div>

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
        const showLogoUrl = record?.image_url || (record?.image ? (record.image.startsWith('http') ? record.image : `/storage/${record.image.replace(/^storage\//, '')}`) : '');

        return (
            <>
                <PageTitle
                    eyebrow="Method Details"
                    title={record?.name || `Method #${id}`}
                    subtitle="Payment channel metadata, gateway mapping, and bank codes."
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
                    <SkeletonFormFields count={5} />
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
                            <span className="label">Method Key / Code</span>
                            <div><span className="badge blue">{record.key}</span></div>
                        </div>

                        <div className="field">
                            <span className="label">Category</span>
                            <div>
                                <span className="badge">
                                    {record.category?.title ? `${record.category.title} (${record.category.key})` : (record.type || '-')}
                                </span>
                            </div>
                        </div>

                        <div className="field">
                            <span className="label">Display Name</span>
                            <div className="input">{record.name}</div>
                        </div>

                        <div className="field">
                            <span className="label">Gateway Provider (From)</span>
                            <div><span className="badge success">{record.from}</span></div>
                        </div>

                        <div className="field">
                            <span className="label">Bank Code</span>
                            <div className="input mono">{record.bankCode || '-'}</div>
                        </div>

                        <div className="field full">
                            <span className="label">Channel Logo</span>
                            <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                                {showLogoUrl ? (
                                    <>
                                        <div style={{
                                            width: '56px',
                                            height: '56px',
                                            borderRadius: '6px',
                                            border: '1px solid var(--border)',
                                            background: '#ffffff',
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                            overflow: 'hidden',
                                        }}>
                                            <img src={showLogoUrl} alt={record.name} style={{ width: '100%', height: '100%', objectFit: 'contain', padding: '4px' }} />
                                        </div>
                                        <CopyButton text={showLogoUrl} />
                                    </>
                                ) : (
                                    <span className="muted">No logo provided</span>
                                )}
                            </div>
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
                eyebrow="Payment Channels"
                title="Payment Methods"
                subtitle="Configure supported payment channels, gateway routing keys, image logos, and bank codes."
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
                            placeholder="Search methods (name, key, code, from)..."
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
                            <option value="10">10 / page</option>
                            <option value="25">25 / page</option>
                            <option value="50">50 / page</option>
                            <option value="100">100 / page</option>
                        </select>
                    </div>
                </div>

                <DataTable columns={['Key', 'Name', 'Category', 'From', 'Bank Code', 'Actions']}>
                    {loading ? (
                        <SkeletonTableRows rows={perPage > 15 ? 10 : 6} columns={6} />
                    ) : records.length > 0 ? (
                        records.map((row: any) => {
                            const primaryKey = row.id || row.key;
                            const tableLogoUrl = row.image_url || (row.image ? (row.image.startsWith('http') ? row.image : `/storage/${row.image.replace(/^storage\//, '')}`) : '');

                            return (
                                <tr key={primaryKey}>
                                    <td className="mono">
                                        <span className="badge blue">{row.key}</span>
                                        <div className="muted mono" style={{ fontSize: '11px' }}>
                                            ID: {row.id}
                                            <CopyButton text={String(row.id)} />
                                        </div>
                                    </td>
                                    <td>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                                            {tableLogoUrl && (
                                                <img
                                                    src={tableLogoUrl}
                                                    alt={row.name}
                                                    style={{
                                                        width: '32px',
                                                        height: '32px',
                                                        objectFit: 'contain',
                                                        borderRadius: '4px',
                                                        background: '#ffffff',
                                                        border: '1px solid var(--border)',
                                                        padding: '2px',
                                                        flexShrink: 0,
                                                    }}
                                                />
                                            )}
                                            <strong>{row.name}</strong>
                                        </div>
                                    </td>
                                    <td>
                                        <span className="badge">
                                            {row.category?.title ? `${row.category.title} (${row.category.key})` : (row.type || '-')}
                                        </span>
                                    </td>
                                    <td><span className="badge success">{row.from || '-'}</span></td>
                                    <td>{row.bankCode || '-'}</td>
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
                            <td colSpan={6} className="empty" style={{ padding: '36px', textAlign: 'center' }}>
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
