import React from 'react';
import { ResourceKey } from '@/features/resource/domain/model/resource_model';
import { displayValue, navigate, title } from '@/shared/utils/format_utils';
import { PageTitle } from '@/shared/component/ui/page_title';
import { DataTable } from '@/shared/component/ui/data_table';
import { CopyButton } from '@/shared/component/ui/copy_button';
import { renderBadge } from '@/shared/component/ui/badge';
import { IconPlus, IconRefresh, IconSearch, IconX } from '@/shared/component/ui/icons';
import { useResourceIndexLogic } from './resource_index_logic';

export interface ResourceIndexProps {
    resource: ResourceKey;
}

export function ResourceIndex({ resource }: ResourceIndexProps): React.JSX.Element {
    const {
        definition,
        records,
        loading,
        error,
        searchTerm,
        setSearchTerm,
        selectedMode,
        selectedStatus,
        perPage,
        total,
        currentPage,
        currentPerPage,
        lastPage,
        isFiltered,
        fetchRecords,
        handleModeChange,
        handleStatusChange,
        handlePerPageChange,
        handleSearchSubmit,
        handleClearFilters,
        destroy,
        resend,
    } = useResourceIndexLogic({ resource });

    return (
        <>
            <PageTitle
                eyebrow="Backoffice"
                title={definition.label}
                subtitle={
                    definition.readonly
                        ? `View and monitor ${definition.label.toLowerCase()} records.`
                        : `Manage, search, and configure ${definition.label.toLowerCase()}.`
                }
            >
                <div className="toolbar">
                    <button
                        type="button"
                        className="button"
                        onClick={() => fetchRecords(currentPage)}
                        title="Reload records"
                        disabled={loading}
                    >
                        <IconRefresh /> Refresh
                    </button>
                    {!definition.readonly && definition.endpoints.create && (
                        <button className="button primary" onClick={() => navigate(`/admin/${resource}/create`)}>
                            <IconPlus /> Create {definition.singular}
                        </button>
                    )}
                </div>
            </PageTitle>

            <div className="panel">
                {/* Search & Filter Toolbar */}
                <div className="filter-bar">
                    <form className="search-box" onSubmit={handleSearchSubmit}>
                        <span className="search-icon"><IconSearch /></span>
                        <input
                            className="search-input"
                            type="text"
                            placeholder={`Search ${definition.label.toLowerCase()} from database...`}
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                        />
                        {searchTerm && (
                            <button
                                className="search-clear"
                                type="button"
                                onClick={() => {
                                    setSearchTerm('');
                                    fetchRecords(1, '', selectedMode, selectedStatus, perPage);
                                }}
                            >
                                <IconX />
                            </button>
                        )}
                    </form>

                    <div className="filter-group">
                        {definition.columns.includes('mode') && (
                            <select
                                className="filter-select"
                                value={selectedMode}
                                onChange={(e) => handleModeChange(e.target.value)}
                            >
                                <option value="all">All Modes</option>
                                <option value="sandbox">Sandbox</option>
                                <option value="prod">Production</option>
                            </select>
                        )}

                        {definition.columns.includes('status') && (
                            <select
                                className="filter-select"
                                value={selectedStatus}
                                onChange={(e) => handleStatusChange(e.target.value)}
                            >
                                <option value="all">All Statuses</option>
                                <option value="SUCCESS">SUCCESS</option>
                                <option value="PENDING">PENDING</option>
                                <option value="FAILED">FAILED</option>
                                <option value="EXPIRED">EXPIRED</option>
                            </select>
                        )}

                        <select
                            className="filter-select"
                            value={perPage}
                            onChange={(e) => handlePerPageChange(Number(e.target.value))}
                        >
                            <option value="15">15 / page</option>
                            <option value="25">25 / page</option>
                            <option value="50">50 / page</option>
                            <option value="100">100 / page</option>
                        </select>

                        {isFiltered && (
                            <button className="button" type="button" onClick={handleClearFilters}>
                                <IconX /> Clear Filter
                            </button>
                        )}

                        <span className="filter-badge">
                            {total} {total === 1 ? 'record' : 'records'}
                        </span>
                    </div>
                </div>

                {/* Error Banner */}
                {error && (
                    <div style={{ padding: '16px 20px' }}>
                        <div className="alert">{error}</div>
                    </div>
                )}

                <DataTable columns={[...definition.columns.map(title), 'Actions']}>
                    {loading ? (
                        <tr>
                            <td className="empty" colSpan={definition.columns.length + 1} style={{ padding: '36px', textAlign: 'center' }}>
                                Loading records from server...
                            </td>
                        </tr>
                    ) : records.length > 0 ? (
                        records.map((record: any) => (
                            <tr key={record.id || record.key || record.reference}>
                                {definition.columns.map((column) => {
                                    const rawVal = record[column];
                                    const isBadgeCol = ['status', 'mode'].includes(column);

                                    if (isBadgeCol) {
                                        return <td key={column}>{renderBadge(column, rawVal)}</td>;
                                    }

                                    const fullValue = displayValue(rawVal || '-');
                                    const max = resource === 'payment-repositories' && column === 'value' ? 180 : null;
                                    const value = max && fullValue.length > max ? `${fullValue.slice(0, max)}...` : fullValue;
                                    const isMono = ['id', 'key', 'reference', 'value', 'callback', 'token'].includes(column);

                                    return (
                                        <td className={isMono ? 'mono' : ''} title={fullValue} key={column}>
                                            {value}
                                            {isMono && rawVal && <CopyButton text={fullValue} />}
                                        </td>
                                    );
                                })}
                                <td>
                                    <div className="actions">
                                        {resource === 'orders' && (
                                            <button
                                                className="button"
                                                onClick={() => navigate(`/admin/${resource}/${record.id}`)}
                                            >
                                                View
                                            </button>
                                        )}
                                        {resource === 'orders' && record.status === 'SUCCESS' && (
                                            <button className="button" onClick={() => resend(record)}>
                                                Resend Callback
                                            </button>
                                        )}
                                        {!definition.readonly && definition.endpoints.update && (
                                            <button
                                                className="button"
                                                onClick={() => navigate(`/admin/${resource}/${record.id}/edit`)}
                                            >
                                                Edit
                                            </button>
                                        )}
                                        {!definition.readonly && definition.endpoints.delete && (
                                            <button className="button danger" onClick={() => destroy(record)}>
                                                Delete
                                            </button>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))
                    ) : (
                        <tr>
                            <td className="empty" colSpan={definition.columns.length + 1} style={{ padding: '36px', textAlign: 'center' }}>
                                {isFiltered ? 'No matching records found from database for this filter.' : 'No records found.'}
                            </td>
                        </tr>
                    )}
                </DataTable>
            </div>

            {/* Pagination Controls */}
            {total > 0 && (
                <div className="pagination">
                    <div>
                        Showing {Math.min((currentPage - 1) * currentPerPage + 1, total)} to {Math.min(currentPage * currentPerPage, total)} of {total} results
                    </div>
                    <div className="pagination-actions">
                        <button
                            className="button"
                            disabled={currentPage <= 1 || loading}
                            onClick={() => fetchRecords(currentPage - 1)}
                        >
                            Previous
                        </button>
                        <span className="button">
                            Page {currentPage} of {lastPage}
                        </span>
                        <button
                            className="button"
                            disabled={currentPage >= lastPage || loading}
                            onClick={() => fetchRecords(currentPage + 1)}
                        >
                            Next
                        </button>
                    </div>
                </div>
            )}
        </>
    );
}
