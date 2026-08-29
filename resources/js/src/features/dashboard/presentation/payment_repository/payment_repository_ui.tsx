import React from 'react';
import { PageTitle } from '@/shared/component/ui/page_title';
import { DataTable } from '@/shared/component/ui/data_table';
import { CopyButton } from '@/shared/component/ui/copy_button';
import { renderBadge } from '@/shared/component/ui/badge';
import { IconPlus, IconRefresh, IconSearch, IconX, IconArrowLeft } from '@/shared/component/ui/icons';
import { navigate, displayValue, formatDate } from '@/shared/utils/format_utils';
import { SkeletonFormFields, SkeletonTableRows } from '@/shared/component/ui/skeleton';
import { ModalDialog } from '@/shared/component/ui/modal_dialog';
import { usePaymentRepositoryLogic } from './payment_repository_logic';

export interface PaymentRepositoryPageProps {
    mode: 'resource-index' | 'resource-create' | 'resource-edit' | 'resource-show';
    id?: string | number;
}

export function PaymentRepositoryPage({ mode, id }: PaymentRepositoryPageProps): React.JSX.Element {
    const {
        isEdit,
        records,
        record,
        form,
        setForm,
        gateways,
        jsonError,
        loading,
        saving,
        error,
        notice,
        testModalRepo,
        testingOrder,
        testOrderResult,
        testForm,
        setTestForm,
        openTestModal,
        closeTestModal,
        handleExecuteTestOrder,
        searchTerm,
        setSearchTerm,
        selectedMode,
        setSelectedMode,
        page,
        perPage,
        setPerPage,
        total,
        currentPage,
        handleSearchSubmit,
        handleClearSearch,
        handleFormSubmit,
        handleDelete,
        loadList,
    } = usePaymentRepositoryLogic({ mode, id });

    if (mode === 'resource-create' || mode === 'resource-edit') {
        return (
            <>
                <PageTitle
                    eyebrow="Gateway Configurations"
                    title={isEdit ? `Edit Repository #${id}` : 'Create Payment Repository'}
                    subtitle="Configure gateway credentials, environment mode, and fee surcharge settings."
                >
                    <div className="toolbar">
                        <button type="button" className="button" onClick={() => navigate('/admin/payment-repositories')}>
                            <IconArrowLeft /> Cancel
                        </button>
                    </div>
                </PageTitle>

                {error && <div className="panel alert danger" style={{ marginBottom: '16px' }}>{error}</div>}
                {jsonError && <div className="panel alert danger" style={{ marginBottom: '16px' }}>{jsonError}</div>}

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
                            <span className="label">Payment Gateway *</span>
                            <select
                                className="input"
                                required
                                value={form.payment_gateway_id}
                                onChange={(e) => setForm({ ...form, payment_gateway_id: e.target.value })}
                            >
                                <option value="">-- Select Payment Gateway --</option>
                                {gateways.map((gw) => (
                                    <option key={gw.id} value={gw.id}>
                                        {gw.name} ({gw.key}) - #{gw.id}
                                    </option>
                                ))}
                            </select>
                        </label>

                        <label className="field">
                            <span className="label">Key / Identifier</span>
                            <input
                                className="input"
                                type="text"
                                placeholder="e.g. default_duitku_sandbox"
                                value={form.key}
                                onChange={(e) => setForm({ ...form, key: e.target.value })}
                            />
                        </label>

                        <label className="field">
                            <span className="label">Environment Mode *</span>
                            <select
                                className="input"
                                value={form.mode}
                                onChange={(e) => setForm({ ...form, mode: e.target.value })}
                            >
                                <option value="sandbox">Sandbox / Test</option>
                                <option value="prod">Production / Live</option>
                            </select>
                        </label>

                        <label className="field full">
                            <span className="label">Configuration JSON (Keys & Surcharges) *</span>
                            <textarea
                                className="input mono"
                                required
                                rows={10}
                                placeholder='{\n  "stripe_secretkey": "sk_...",\n  "stripe_publishablekey": "pk_...",\n  "surcharge_mode": "middleware_calc",\n  "surcharge_percent": 2.9,\n  "surcharge_fixed": 2000,\n  "surcharge_label": "Processing Fee"\n}'
                                value={form.value}
                                onChange={(e) => setForm({ ...form, value: e.target.value })}
                            />
                        </label>

                        <div className="field full" style={{ display: 'flex', gap: '12px', marginTop: '12px' }}>
                            <button className="button primary" type="submit" disabled={saving}>
                                {saving ? 'Saving...' : isEdit ? 'Update Repository' : 'Create Repository'}
                            </button>
                        </div>
                    </form>
                </div>
            </>
        );
    }

    if (mode === 'resource-show') {
        const jsonValue = record?.value ? displayValue(record.value) : '';

        return (
            <>
                <PageTitle
                    eyebrow="Repository Details"
                    title={`Repository #${id}`}
                    subtitle="Gateway secret keys, webhook tokens, and surcharge settings."
                >
                    <div className="toolbar">
                        <button type="button" className="button" onClick={() => navigate('/admin/payment-repositories')}>
                            <IconArrowLeft /> Back to Repositories
                        </button>
                        {record && (
                            <button
                                type="button"
                                className="button"
                                onClick={() => openTestModal(record)}
                                title="Simulate create order on this gateway repository"
                            >
                                🧪 Test Create Order
                            </button>
                        )}
                        <button type="button" className="button primary" onClick={() => navigate(`/admin/payment-repositories/${id}/edit`)}>
                            Edit Repository
                        </button>
                    </div>
                </PageTitle>

                {error && <div className="panel alert danger" style={{ marginBottom: '16px' }}>{error}</div>}

                {loading ? (
                    <SkeletonFormFields count={5} />
                ) : record ? (
                    <>
                        <div className="panel form-grid" style={{ marginBottom: '24px' }}>
                            <div className="field">
                                <span className="label">Repository ID</span>
                                <div className="input mono" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                    <span>#{record.id}</span>
                                    <CopyButton text={String(record.id)} />
                                </div>
                            </div>

                            <div className="field">
                                <span className="label">Gateway Name</span>
                                <div className="input" style={{ fontWeight: 600 }}>
                                    {record.payment_gateway?.name || `Gateway #${record.payment_gateway_id}`}
                                </div>
                            </div>

                            <div className="field">
                                <span className="label">Identifier Key</span>
                                <div><span className="badge blue">{record.key || '-'}</span></div>
                            </div>

                            <div className="field">
                                <span className="label">Environment Mode</span>
                                <div>{renderBadge('mode', record.mode)}</div>
                            </div>

                            <div className="field">
                                <span className="label">Created At</span>
                                <div className="input">{formatDate(record.created_at)}</div>
                            </div>
                        </div>

                        <div className="panel" style={{ padding: '24px' }}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px' }}>
                                <div className="panel-title" style={{ fontSize: '15px' }}>Configuration Credentials JSON</div>
                                {jsonValue && <CopyButton text={jsonValue} />}
                            </div>

                            <pre
                                className="mono"
                                style={{
                                    margin: 0,
                                    padding: '16px',
                                    background: 'var(--bg-page)',
                                    border: '1px solid var(--border)',
                                    borderRadius: 'var(--radius-md)',
                                    whiteSpace: 'pre-wrap',
                                    maxHeight: '360px',
                                    overflowY: 'auto',
                                    fontSize: '12px',
                                }}
                            >
                                {jsonValue || '{}'}
                            </pre>
                        </div>
                    </>
                ) : (
                    <div className="panel empty">Payment repository not found.</div>
                )}
            </>
        );
    }

    return (
        <>
            <PageTitle
                eyebrow="Gateway Credentials"
                title="Payment Repositories"
                subtitle="Manage API keys, merchant secret configurations, and surcharges across gateway drivers."
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
                        onClick={() => navigate('/admin/payment-repositories/create')}
                    >
                        <IconPlus /> Create Repository
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
                            placeholder="Search repositories (key, gateway id)..."
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
                            value={perPage}
                            onChange={(e) => setPerPage(Number(e.target.value))}
                        >
                            <option value="10">10 / page</option>
                            <option value="25">25 / page</option>
                            <option value="50">50 / page</option>
                        </select>
                    </div>
                </div>

                <DataTable columns={['ID', 'Gateway Name', 'Key', 'Mode', 'Config Summary', 'Created', 'Actions']}>
                    {loading ? (
                        <SkeletonTableRows rows={perPage > 15 ? 10 : 6} columns={7} />
                    ) : records.length > 0 ? (
                        records.map((row: any) => {
                            const primaryKey = row.id;
                            const isProd = row.mode === 'prod';
                            const valKeys = row.value && typeof row.value === 'object' ? Object.keys(row.value).join(', ') : 'JSON Config';
                            return (
                                <tr key={primaryKey}>
                                    <td className="mono">
                                        #{row.id}
                                        <CopyButton text={String(row.id)} />
                                    </td>
                                    <td>
                                        <strong>{row.payment_gateway?.name || `Gateway #${row.payment_gateway_id}`}</strong>
                                        <div className="muted mono" style={{ fontSize: '11px' }}>
                                            ID: {row.payment_gateway_id}
                                        </div>
                                    </td>
                                    <td><span className="badge blue">{row.key || '-'}</span></td>
                                    <td>{renderBadge('mode', row.mode)}</td>
                                    <td style={{ maxWidth: '240px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', fontSize: '12px' }}>
                                        <span className="muted">{valKeys}</span>
                                    </td>
                                    <td>{formatDate(row.created_at)}</td>
                                    <td>
                                        <div className="actions">
                                            <button
                                                type="button"
                                                className="button"
                                                onClick={() => openTestModal(row)}
                                                title="Test create order on this gateway"
                                            >
                                                Test
                                            </button>
                                            <button
                                                className="button"
                                                onClick={() => navigate(`/admin/payment-repositories/${primaryKey}`)}
                                            >
                                                View
                                            </button>
                                            <button
                                                className="button"
                                                onClick={() => navigate(`/admin/payment-repositories/${primaryKey}/edit`)}
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
                                No payment repositories found.
                            </td>
                        </tr>
                    )}
                </DataTable>
            </div>

            {total > perPage && (
                <div className="pagination">
                    <div>
                        Showing page {currentPage} of {Math.ceil(total / perPage)} ({total} total repositories)
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

            {/* Test Create Order Simulation Modal */}
            <ModalDialog
                isOpen={Boolean(testModalRepo)}
                title={`🧪 Test Create Order: ${testModalRepo?.payment_gateway?.name || testModalRepo?.key || 'Gateway'}`}
                description={`Simulate real API order creation on ${testModalRepo?.mode?.toUpperCase() || 'SANDBOX'} mode.`}
                confirmText={testOrderResult ? 'Re-run Test Order' : 'Create Test Order'}
                cancelText="Close"
                confirmTone="primary"
                loading={testingOrder}
                onConfirm={handleExecuteTestOrder as any}
                onClose={closeTestModal}
            >
                {testModalRepo && (
                    <form onSubmit={handleExecuteTestOrder} style={{ display: 'flex', flexDirection: 'column', gap: '14px' }}>
                        {testModalRepo.mode === 'prod' && (
                            <div className="alert warning" style={{ fontSize: '12px', margin: 0 }}>
                                ⚠️ <strong>Caution:</strong> This repository is configured for <strong>PRODUCTION / LIVE</strong> mode. Creating a test order will generate a real transaction on the live payment gateway engine.
                            </div>
                        )}

                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
                            <label className="field" style={{ margin: 0 }}>
                                <span className="label">Amount</span>
                                <input
                                    className="input"
                                    type="number"
                                    required
                                    value={testForm.amount}
                                    onChange={(e) => setTestForm({ ...testForm, amount: Number(e.target.value) })}
                                />
                            </label>

                            <label className="field" style={{ margin: 0 }}>
                                <span className="label">Currency</span>
                                <input
                                    className="input mono"
                                    type="text"
                                    required
                                    value={testForm.currency}
                                    onChange={(e) => setTestForm({ ...testForm, currency: e.target.value.toUpperCase() })}
                                />
                            </label>
                        </div>

                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
                            <label className="field" style={{ margin: 0 }}>
                                <span className="label">Buyer Email</span>
                                <input
                                    className="input"
                                    type="email"
                                    required
                                    value={testForm.email}
                                    onChange={(e) => setTestForm({ ...testForm, email: e.target.value })}
                                />
                            </label>

                            <label className="field" style={{ margin: 0 }}>
                                <span className="label">Buyer Name</span>
                                <input
                                    className="input"
                                    type="text"
                                    value={testForm.name}
                                    onChange={(e) => setTestForm({ ...testForm, name: e.target.value })}
                                />
                            </label>
                        </div>

                        {/* Test Results Section */}
                        {testOrderResult && (
                            <div style={{ marginTop: '12px', borderTop: '1px solid var(--border)', paddingTop: '14px' }}>
                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' }}>
                                    <strong style={{ color: 'var(--success)', fontSize: '13px' }}>
                                        ✅ Test Order Successfully Created!
                                    </strong>
                                </div>

                                {testOrderResult.checkout_url && (
                                    <div style={{ marginBottom: '10px' }}>
                                        <span className="label" style={{ fontSize: '11px' }}>Payment / Checkout URL:</span>
                                        <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
                                            <a
                                                href={testOrderResult.checkout_url}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="button primary"
                                                style={{ fontSize: '12px', padding: '0 12px', minHeight: '32px' }}
                                            >
                                                👉 Open Payment Checkout Page
                                            </a>
                                            <CopyButton text={testOrderResult.checkout_url} />
                                        </div>
                                    </div>
                                )}

                                <div>
                                    <span className="label" style={{ fontSize: '11px' }}>Gateway Response:</span>
                                    <pre
                                        className="mono"
                                        style={{
                                            margin: 0,
                                            padding: '10px',
                                            background: 'var(--bg-page)',
                                            border: '1px solid var(--border)',
                                            borderRadius: 'var(--radius-sm)',
                                            maxHeight: '180px',
                                            overflowY: 'auto',
                                            fontSize: '11px',
                                        }}
                                    >
                                        {displayValue(testOrderResult.raw_result || testOrderResult)}
                                    </pre>
                                </div>
                            </div>
                        )}
                    </form>
                )}
            </ModalDialog>
        </>
    );
}
