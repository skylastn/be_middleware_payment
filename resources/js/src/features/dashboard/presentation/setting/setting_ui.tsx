import React from 'react';
import { PageTitle } from '@/shared/component/ui/page_title';
import { DataTable } from '@/shared/component/ui/data_table';
import { CopyButton } from '@/shared/component/ui/copy_button';
import { IconPlus, IconRefresh, IconSearch, IconX, IconArrowLeft } from '@/shared/component/ui/icons';
import { navigate } from '@/shared/utils/format_utils';
import { SkeletonFormFields, SkeletonTableRows } from '@/shared/component/ui/skeleton';
import { useSettingLogic } from './setting_logic';

export interface SettingPageProps {
    mode: 'resource-index' | 'resource-create' | 'resource-edit' | 'resource-show';
    id?: string | number;
}

export function SettingPage({ mode, id }: SettingPageProps): React.JSX.Element {
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
    } = useSettingLogic({ mode, id });

    if (mode === 'resource-create' || mode === 'resource-edit') {
        return (
            <>
                <PageTitle
                    eyebrow="System Configuration"
                    title={isEdit ? `Edit Setting: ${form.key || id}` : 'Create Setting'}
                    subtitle="Configure system variables and middleware properties."
                >
                    <div className="toolbar">
                        <button type="button" className="button" onClick={() => navigate('/admin/settings')}>
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

                        <label className="field full">
                            <span className="label">Setting Key *</span>
                            <input
                                className="input"
                                type="text"
                                required
                                placeholder="e.g. system_maintenance, default_currency"
                                value={form.key}
                                onChange={(e) => setForm({ ...form, key: e.target.value })}
                            />
                        </label>

                        <label className="field full">
                            <span className="label">Setting Value *</span>
                            <textarea
                                className="input"
                                required
                                rows={6}
                                placeholder="Value content or stringified configuration"
                                value={form.value}
                                onChange={(e) => setForm({ ...form, value: e.target.value })}
                            />
                        </label>

                        <div className="field full" style={{ display: 'flex', gap: '12px', marginTop: '12px' }}>
                            <button className="button primary" type="submit" disabled={saving}>
                                {saving ? 'Saving...' : isEdit ? 'Update Setting' : 'Create Setting'}
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
                    eyebrow="Setting Details"
                    title={record?.key || `Setting #${id}`}
                    subtitle="System configuration variable details."
                >
                    <div className="toolbar">
                        <button type="button" className="button" onClick={() => navigate('/admin/settings')}>
                            <IconArrowLeft /> Back to Settings
                        </button>
                        <button type="button" className="button primary" onClick={() => navigate(`/admin/settings/${id}/edit`)}>
                            Edit Setting
                        </button>
                    </div>
                </PageTitle>

                {error && <div className="panel alert danger" style={{ marginBottom: '16px' }}>{error}</div>}

                {loading ? (
                    <SkeletonFormFields count={3} />
                ) : record ? (
                    <div className="panel form-grid">
                        <div className="field">
                            <span className="label">Setting ID</span>
                            <div className="input mono" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                <span>#{record.id}</span>
                                <CopyButton text={String(record.id)} />
                            </div>
                        </div>

                        <div className="field">
                            <span className="label">Key</span>
                            <div><span className="badge blue mono">{record.key}</span></div>
                        </div>

                        <div className="field full">
                            <span className="label">Value</span>
                            <div className="input mono" style={{ whiteSpace: 'pre-wrap' }}>{record.value || '-'}</div>
                        </div>

                        <div className="field">
                            <span className="label">Updated At</span>
                            <div className="input">{record.updated_at || '-'}</div>
                        </div>
                    </div>
                ) : (
                    <div className="panel empty">Setting not found.</div>
                )}
            </>
        );
    }

    return (
        <>
            <PageTitle
                eyebrow="System Configuration"
                title="System Settings"
                subtitle="Manage runtime configuration parameters, feature flags, and global variables."
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
                        onClick={() => navigate('/admin/settings/create')}
                    >
                        <IconPlus /> Create Setting
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
                            placeholder="Search settings (key, value)..."
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

                <DataTable columns={['Key', 'Value', 'Updated', 'Actions']}>
                    {loading ? (
                        <SkeletonTableRows rows={perPage > 15 ? 10 : 6} columns={4} />
                    ) : records.length > 0 ? (
                        records.map((row: any) => {
                            const primaryKey = row.id || row.key;
                            return (
                                <tr key={primaryKey}>
                                    <td>
                                        <span className="badge blue mono">{row.key}</span>
                                        <div className="muted mono" style={{ fontSize: '11px' }}>
                                            ID: {row.id}
                                            <CopyButton text={String(row.id)} />
                                        </div>
                                    </td>
                                    <td style={{ maxWidth: '360px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                                        {row.value}
                                    </td>
                                    <td>{row.updated_at || '-'}</td>
                                    <td>
                                        <div className="actions">
                                            <button
                                                className="button"
                                                onClick={() => navigate(`/admin/settings/${primaryKey}`)}
                                            >
                                                View
                                            </button>
                                            <button
                                                className="button"
                                                onClick={() => navigate(`/admin/settings/${primaryKey}/edit`)}
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
                            <td colSpan={4} className="empty" style={{ padding: '36px', textAlign: 'center' }}>
                                No settings found.
                            </td>
                        </tr>
                    )}
                </DataTable>
            </div>

            {total > perPage && (
                <div className="pagination">
                    <div>
                        Showing page {currentPage} of {Math.ceil(total / perPage)} ({total} total settings)
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
