import React, { useEffect, useState } from 'react';
import { ResourceKey } from '../../model/resource_model';
import { resourceDefinitions } from '../../shared/constant/resource_definitions';
import { ResourceService } from '../../services/resource_service';
import { dataRecord, displayValue, navigate, title } from '../../shared/utils/format_utils';
import { PageTitle } from '../../shared/widget/page_title';
import { CopyButton } from '../../shared/widget/copy_button';
import { IconArrowLeft } from '../../shared/widget/icons';

export interface ResourceShowProps {
    resource: ResourceKey;
    id: string;
}

export function ResourceShow({ resource, id }: ResourceShowProps): React.JSX.Element {
    const definition = resourceDefinitions[resource];
    const [payload, setPayload] = useState<any>(null);
    const [error, setError] = useState<string>('');

    useEffect(() => {
        ResourceService.show(definition.endpoints.show(id))
            .then(setPayload)
            .catch((exception: Error) => setError(exception.message));
    }, [resource, id]);

    if (error) {
        return <div className="panel empty">{error}</div>;
    }

    if (!payload) {
        return <div className="panel empty">Loading record details...</div>;
    }

    const record = dataRecord(payload);

    return (
        <>
            <PageTitle
                eyebrow="Audit Detail"
                title={`View ${definition.singular} Details`}
                subtitle="Complete operational metadata and transaction payload."
            >
                <button className="button" onClick={() => navigate(`/admin/${resource}`)}>
                    <IconArrowLeft /> Back to List
                </button>
            </PageTitle>
            <div className="panel detail-list">
                {Object.entries(record).map(([name, value]) => (
                    <div className={`detail-row ${displayValue(value).length > 80 ? 'wide' : ''}`} key={name}>
                        <span className="label">{title(name)}</span>
                        <div className="mono">
                            {displayValue(value) || '-'}
                            {value && <CopyButton text={displayValue(value)} />}
                        </div>
                    </div>
                ))}
            </div>
        </>
    );
}
