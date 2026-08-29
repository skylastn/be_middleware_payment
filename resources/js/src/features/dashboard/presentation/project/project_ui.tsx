import React from 'react';
import { PageTitle } from '@/shared/component/ui/page_title';
import { DataTable } from '@/shared/component/ui/data_table';
import { CopyButton } from '@/shared/component/ui/copy_button';
import { IconArrowLeft, IconPlus, IconRefresh, IconSearch, IconX } from '@/shared/component/ui/icons';
import { formatDate, navigate } from '@/shared/utils/format_utils';
import { SkeletonFormFields, SkeletonTableRows } from '@/shared/component/ui/skeleton';
import { useProjectLogic } from './project_logic';

export interface ProjectPageProps {
    mode: 'resource-index' | 'resource-create' | 'resource-edit' | 'resource-show';
    id?: string | number;
}

export function ProjectPage({ mode, id }: ProjectPageProps): React.JSX.Element {
    const {
        isEdit,
        records,
        record,
        form,
        setForm,
        loading,
        saving,
        syncing,
        error,
        notice,
        searchTerm,
        setSearchTerm,
        selectedSlug,
        setSelectedSlug,
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
        handleSyncMissingLog,
    } = useProjectLogic({ mode, id });

    if (mode === 'resource-create' || mode === 'resource-edit') {
        return (
            <>
                <PageTitle
                    eyebrow="Merchant Apps"
                    title={isEdit ? `Edit Project: ${form.name || id}` : 'Create New Project'}
                    subtitle="Configure merchant identification, slug routing, and callback endpoints."
                >
                    <div className="toolbar">
                        <button type="button" className="button" onClick={() => navigate('/admin/projects')}>
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
                            <span className="label">Project Name *</span>
                            <input
                                className="input"
                                type="text"
                                required
                                value={form.name}
                                onChange={(e) => setForm({ ...form, name: e.target.value })}
                            />
                        </label>

                        <label className="field">
                            <span className="label">Type Code *</span>
                            <input
                                className="input"
                                type="text"
                                required
                                placeholder="e.g. SHOP, STORE, PORTAL"
                                value={form.type}
                                onChange={(e) => setForm({ ...form, type: e.target.value })}
                            />
                        </label>

                        <label className="field">
                            <span className="label">Default Gateway Slug *</span>
                            <select
                                className="input"
                                value={form.slug}
                                onChange={(e) => setForm({ ...form, slug: e.target.value })}
                            >
                                <option value="duitku">Duitku</option>
                                <option value="midtrans">Midtrans</option>
                                <option value="xendit">Xendit</option>
                                <option value="spnpay">SPNPay</option>
                                <option value="stripe">Stripe</option>
                            </select>
                        </label>

                        <label className="field full">
                            <span className="label">Merchant Callback URL *</span>
                            <textarea
                                className="input"
                                required
                                placeholder="https://merchant.example.com/api/payment/callback"
                                value={form.callback}
                                onChange={(e) => setForm({ ...form, callback: e.target.value })}
                            />
                        </label>

                        {/* Display readonly credential fields if in edit mode */}
                        {isEdit && record && (
                            <>
                                <label className="field">
                                    <span className="label">API Key (Generated)</span>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                        <input className="input mono" type="text" value={record.key || ''} disabled readOnly />
                                        <CopyButton text={record.key || ''} />
                                    </div>
                                </label>
                                <label className="field">
                                    <span className="label">Secure Key (Generated)</span>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                        <input className="input mono" type="text" value={record.secure || ''} disabled readOnly />
                                        <CopyButton text={record.secure || ''} />
                                    </div>
                                </label>
                                <label className="field full">
                                    <span className="label">Auth Token (Header 'Token')</span>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                        <input className="input mono" type="text" value={record.value || ''} disabled readOnly />
                                        <CopyButton text={record.value || ''} />
                                    </div>
                                </label>
                            </>
                        )}

                        <div className="field full" style={{ display: 'flex', gap: '12px', marginTop: '12px' }}>
                            <button className="button primary" type="submit" disabled={saving}>
                                {saving ? 'Saving...' : isEdit ? 'Update Project' : 'Create Project'}
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
                    eyebrow="Project Details"
                    title={record?.name || `Project #${id}`}
                    subtitle="Inspect project configuration, merchant credentials, and callback setup."
                >
                    <div className="toolbar">
                        <button type="button" className="button" onClick={() => navigate('/admin/projects')}>
                            <IconArrowLeft /> Back to Projects
                        </button>
                        <button type="button" className="button primary" onClick={() => navigate(`/admin/projects/${id}/edit`)}>
                            Edit Project
                        </button>
                    </div>
                </PageTitle>

                {error && <div className="panel alert danger" style={{ marginBottom: '16px' }}>{error}</div>}

                {loading ? (
                    <SkeletonFormFields count={8} />
                ) : record ? (
                    <div className="panel form-grid">
                        <div className="field">
                            <span className="label">Project ID</span>
                            <div className="input mono" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                <span>{record.id}</span>
                                <CopyButton text={String(record.id)} />
                            </div>
                        </div>

                        <div className="field">
                            <span className="label">Project Name</span>
                            <div className="input">{record.name}</div>
                        </div>

                        <div className="field">
                            <span className="label">Type Code</span>
                            <div className="input mono">{record.type}</div>
                        </div>

                        <div className="field">
                            <span className="label">Slug Routing</span>
                            <div><span className="badge blue">{record.slug}</span></div>
                        </div>

                        <div className="field full">
                            <span className="label">Callback URL</span>
                            <div className="input mono">{record.callback || '-'}</div>
                        </div>

                        <div className="field">
                            <span className="label">API Key</span>
                            <div className="input mono" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                <span>{record.key || '-'}</span>
                                {record.key && <CopyButton text={record.key} />}
                            </div>
                        </div>

                        <div className="field">
                            <span className="label">Secure Key</span>
                            <div className="input mono" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                <span>{record.secure || '-'}</span>
                                {record.secure && <CopyButton text={record.secure} />}
                            </div>
                        </div>

                        <div className="field full">
                            <span className="label">Auth Token (Header 'Token')</span>
                            <div className="input mono" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                <span>{record.value || '-'}</span>
                                {record.value && <CopyButton text={record.value} />}
                            </div>
                        </div>
                    </div>
                ) : (
                    <div className="panel empty">Project not found.</div>
                )}
            </>
        );
    }

    return (
        <>
            <PageTitle
                eyebrow="Registered Applications"
                title="Projects Management"
                subtitle="Manage merchant applications, webhook callback endpoints, and generated credentials."
            >
                <div className="toolbar">
                    <button
                        type="button"
                        className="button"
                        disabled={syncing}
                        onClick={handleSyncMissingLog}
                        title="Sync missing log tables"
                    >
                        <IconRefresh /> {syncing ? 'Syncing...' : 'Sync Missing Logs'}
                    </button>
                    <button
                        type="button"
                        className="button primary"
                        onClick={() => navigate('/admin/projects/create')}
                    >
                        <IconPlus /> Create Project
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
                            placeholder="Search projects (name, type, callback)..."
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
                            value={selectedSlug}
                            onChange={(e) => setSelectedSlug(e.target.value)}
                        >
                            <option value="all">All Gateways</option>
                            <option value="duitku">Duitku</option>
                            <option value="midtrans">Midtrans</option>
                            <option value="xendit">Xendit</option>
                            <option value="spnpay">SPNPay</option>
                            <option value="stripe">Stripe</option>
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

                <DataTable columns={['Name', 'Type', 'Slug', 'Callback URL', 'Created', 'Actions']}>
                    {loading ? (
                        <SkeletonTableRows rows={perPage > 15 ? 10 : 6} columns={6} />
                    ) : records.length > 0 ? (
                        records.map((row: any) => {
                            const primaryKey = row.id || row.key || row.name;
                            return (
                                <tr key={primaryKey}>
                                    <td>
                                        <strong>{row.name}</strong>
                                        <div className="muted mono" style={{ fontSize: '11px' }}>
                                            ID: {row.id}
                                            <CopyButton text={String(row.id)} />
                                        </div>
                                    </td>
                                    <td><span className="badge blue">{row.type}</span></td>
                                    <td><span className="badge">{row.slug}</span></td>
                                    <td style={{ maxWidth: '300px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                                        {row.callback}
                                    </td>
                                    <td>{formatDate(row.created_at)}</td>
                                    <td>
                                        <div className="actions">
                                            <button
                                                className="button"
                                                onClick={() => navigate(`/admin/projects/${primaryKey}`)}
                                            >
                                                View
                                            </button>
                                            <button
                                                className="button"
                                                onClick={() => navigate(`/admin/projects/${primaryKey}/edit`)}
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
                                No projects found.
                            </td>
                        </tr>
                    )}
                </DataTable>
            </div>

            {total > perPage && (
                <div className="pagination">
                    <div>
                        Showing page {currentPage} of {Math.ceil(total / perPage)} ({total} total projects)
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
