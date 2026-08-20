import React, { useEffect, useState } from 'react';
import { ResourceKey } from '../../model/resource_model';
import { resourceDefinitions } from '../../shared/constant/resource_definitions';
import { ResourceService } from '../../services/resource_service';
import { dataItems, displayValue, navigate, title } from '../../shared/utils/format_utils';
import { PaginationInfo } from '../../model/response_model';
import { PageTitle } from '../../shared/widget/page_title';
import { DataTable } from '../../shared/widget/data_table';
import { CopyButton } from '../../shared/widget/copy_button';
import { renderBadge } from '../../shared/widget/badge';
import { IconPlus, IconSearch, IconX } from '../../shared/widget/icons';

export interface ResourceIndexProps {
    resource: ResourceKey;
}

export function ResourceIndex({ resource }: ResourceIndexProps): React.JSX.Element {
    const definition = resourceDefinitions[resource];
    const [payload, setPayload] = useState<any>(null);
    const [error, setError] = useState<string>('');
    const [searchTerm, setSearchTerm] = useState<string>('');
    const [selectedMode, setSelectedMode] = useState<string>('all');
    const [selectedStatus, setSelectedStatus] = useState<string>('all');

    const load = (url = definition.endpoints.list) => {
        setError('');
        ResourceService.list(url)
            .then(setPayload)
            .catch((exception: Error) => setError(exception.message));
    };

    useEffect(() => {
        setPayload(null);
        setSearchTerm('');
        setSelectedMode('all');
        setSelectedStatus('all');
        load();
    }, [resource]);

    if (error) {
        return <div className="panel empty">{error}</div>;
    }

    if (!payload) {
        return <div className="panel empty">Loading records...</div>;
    }

    const records = dataItems(payload);
    const perPage = Number(payload?.perPage || 15);
    const total = Number(payload?.total || records.length);
    const currentPage = Number(payload?.currentPage || 1);

    const page: PaginationInfo = {
        currentPage,
        lastPage: Math.max(1, Math.ceil(total / Math.max(perPage, 1))),
        from: total ? (currentPage - 1) * perPage + 1 : 0,
        to: Math.min(currentPage * perPage, total),
        total,
        previousPageUrl: currentPage > 1 ? `${window.location.pathname}?page=${currentPage - 1}` : null,
        nextPageUrl: currentPage * perPage < total ? `${window.location.pathname}?page=${currentPage + 1}` : null,
    };

    // Instant Filter & Search across all columns
    const filteredRecords = records.filter((record: Record<string, any>) => {
        if (searchTerm.trim()) {
            const term = searchTerm.toLowerCase().trim();
            const matches = Object.values(record).some((val) => {
                if (val === null || val === undefined) return false;
                if (typeof val === 'object') {
                    return JSON.stringify(val).toLowerCase().includes(term);
                }
                return String(val).toLowerCase().includes(term);
            });
            if (!matches) return false;
        }

        if (selectedMode !== 'all' && record.mode) {
            if (String(record.mode).toLowerCase() !== selectedMode.toLowerCase()) {
                return false;
            }
        }

        if (selectedStatus !== 'all' && record.status) {
            if (String(record.status).toLowerCase() !== selectedStatus.toLowerCase()) {
                return false;
            }
        }

        return true;
    });

    const isFiltered = searchTerm.trim() !== '' || selectedMode !== 'all' || selectedStatus !== 'all';

    const clearFilters = () => {
        setSearchTerm('');
        setSelectedMode('all');
        setSelectedStatus('all');
    };

    async function destroy(record: any) {
        if (!definition.endpoints.delete) return;
        if (!window.confirm(`Are you sure you want to delete ${definition.singular} #${record.id || record.key}?`)) {
            return;
        }

        await ResourceService.delete(definition.endpoints.delete(record.id));
        load();
    }

    async function resend(record: any) {
        if (!definition.endpoints.resend) return;
        if (!window.confirm(`Resend merchant callback for order ${record.reference}?`)) {
            return;
        }

        await ResourceService.resendCallback(definition.endpoints.resend(record.id));
        load();
    }

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
                {!definition.readonly && definition.endpoints.create && (
                    <button className="button primary" onClick={() => navigate(`/admin/${resource}/create`)}>
                        <IconPlus /> Create {definition.singular}
                    </button>
                )}
            </PageTitle>

            <div className="panel">
                {/* Search & Filter Toolbar */}
                <div className="filter-bar">
                    <div className="search-box">
                        <span className="search-icon"><IconSearch /></span>
                        <input
                            className="search-input"
                            type="text"
                            placeholder={`Search in ${definition.label.toLowerCase()}...`}
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                        />
                        {searchTerm && (
                            <button className="search-clear" type="button" onClick={() => setSearchTerm('')}>
                                <IconX />
                            </button>
                        )}
                    </div>

                    <div className="filter-group">
                        {definition.columns.includes('mode') && (
                            <select
                                className="filter-select"
                                value={selectedMode}
                                onChange={(e) => setSelectedMode(e.target.value)}
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
                                onChange={(e) => setSelectedStatus(e.target.value)}
                            >
                                <option value="all">All Statuses</option>
                                <option value="SUCCESS">SUCCESS</option>
                                <option value="PENDING">PENDING</option>
                                <option value="FAILED">FAILED</option>
                                <option value="EXPIRED">EXPIRED</option>
                            </select>
                        )}

                        {isFiltered && (
                            <button className="button" type="button" onClick={clearFilters}>
                                <IconX /> Clear Filter
                            </button>
                        )}

                        <span className="filter-badge">
                            {filteredRecords.length} {filteredRecords.length === 1 ? 'record' : 'records'}
                        </span>
                    </div>
                </div>

                <DataTable columns={[...definition.columns.map(title), 'Actions']}>
                    {filteredRecords.length ? (
                        filteredRecords.map((record: any) => (
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
                            <td className="empty" colSpan={definition.columns.length + 1}>
                                {isFiltered ? 'No matching records found for this filter.' : 'No records found.'}
                            </td>
                        </tr>
                    )}
                </DataTable>
            </div>

            <div className="pagination">
                <div>
                    Showing {filteredRecords.length} {isFiltered ? `(filtered from ${page.total})` : `of ${page.total}`} results
                </div>
                <div className="pagination-actions">
                    <button
                        className="button"
                        disabled={page.currentPage <= 1}
                        onClick={() => load(`${definition.endpoints.list}?page=${page.currentPage - 1}`)}
                    >
                        Previous
                    </button>
                    <span className="button">
                        Page {page.currentPage} of {page.lastPage}
                    </span>
                    <button
                        className="button"
                        disabled={page.currentPage >= page.lastPage}
                        onClick={() => load(`${definition.endpoints.list}?page=${page.currentPage + 1}`)}
                    >
                        Next
                    </button>
                </div>
            </div>
        </>
    );
}
