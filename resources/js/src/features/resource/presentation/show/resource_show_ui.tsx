import React from 'react';
import { ResourceKey } from '@/features/resource/domain/model/resource_model';
import { navigate } from '@/shared/utils/format_utils';
import { PageTitle } from '@/shared/component/ui/page_title';
import { renderBadge } from '@/shared/component/ui/badge';
import { CopyButton } from '@/shared/component/ui/copy_button';
import { IconArrowLeft, IconRefresh } from '@/shared/component/ui/icons';
import { useResourceShowLogic } from './resource_show_logic';

export interface ResourceShowProps {
    resource: ResourceKey;
    id: string;
}

export function ResourceShow({ resource, id }: ResourceShowProps): React.JSX.Element {
    const { definition, record, loading, error, reload, resend } = useResourceShowLogic({ resource, id });

    if (error) {
        return <div className="panel empty">{error}</div>;
    }

    if (!record && loading) {
        return <div className="panel empty">Loading {definition.singular.toLowerCase()} details...</div>;
    }

    if (!record) {
        return <div className="panel empty">No record found.</div>;
    }

    return (
        <>
            <PageTitle
                eyebrow="Record View"
                title={`${definition.singular} #${record.reference || record.id || record.key}`}
                subtitle={`Detailed transaction inspect and payload attributes for ${definition.singular.toLowerCase()}.`}
            >
                <div className="toolbar">
                    <button className="button" onClick={() => navigate(`/admin/${resource}`)}>
                        <IconArrowLeft /> Back to List
                    </button>
                    {resource === 'orders' && record.status === 'SUCCESS' && (
                        <button className="button primary" onClick={resend}>
                            Resend Callback
                        </button>
                    )}
                    <button type="button" className="button" onClick={reload} title="Refresh details" disabled={loading}>
                        <IconRefresh /> Refresh
                    </button>
                </div>
            </PageTitle>

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
                        {record.id && <CopyButton text={record.id} />}
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

                <div className="field">
                    <span className="label">Customer Email</span>
                    <div className="input">{record.email || '-'}</div>
                </div>

                <div className="field">
                    <span className="label">Customer Phone</span>
                    <div className="input">{record.phone || '-'}</div>
                </div>
            </div>

            <div className="panel" style={{ padding: '24px' }}>
                <div className="panel-title" style={{ fontSize: '15px', marginBottom: '16px' }}>
                    Raw Payload Inspection
                </div>

                <div className="form-grid">
                    {['request', 'response', 'callback'].map((payloadKey) => {
                        const payloadData = record[payloadKey];
                        const jsonString =
                            typeof payloadData === 'object' && payloadData !== null
                                ? JSON.stringify(payloadData, null, 2)
                                : typeof payloadData === 'string' && payloadData.trim()
                                ? (() => {
                                      try {
                                          return JSON.stringify(JSON.parse(payloadData), null, 2);
                                      } catch {
                                          return payloadData;
                                      }
                                  })()
                                : '-';

                        return (
                            <div className="field full" key={payloadKey}>
                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '4px' }}>
                                    <span className="label" style={{ textTransform: 'capitalize' }}>
                                        {payloadKey} Payload
                                    </span>
                                    {jsonString !== '-' && <CopyButton text={jsonString} />}
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
                                    {jsonString}
                                </pre>
                            </div>
                        );
                    })}
                </div>
            </div>
        </>
    );
}
