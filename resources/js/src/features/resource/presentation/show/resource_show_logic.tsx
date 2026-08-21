import { useEffect, useState } from 'react';
import { ResourceKey } from '@/features/resource/domain/model/resource_model';
import { resourceDefinitions } from '@/features/resource/domain/constant/resource_definitions';
import { ResourceService } from '@/features/resource/application/resource_service';
import { dataRecord } from '@/shared/utils/format_utils';

export interface UseResourceShowLogicProps {
    resource: ResourceKey;
    id: string;
}

export function useResourceShowLogic({ resource, id }: UseResourceShowLogicProps) {
    const definition = resourceDefinitions[resource];
    const [record, setRecord] = useState<any>(null);
    const [loading, setLoading] = useState<boolean>(true);
    const [error, setError] = useState<string>('');

    const loadRecord = () => {
        setLoading(true);
        setError('');
        ResourceService.show(definition.endpoints.show!(id))
            .then((payload) => {
                setRecord(dataRecord(payload));
            })
            .catch((exception: Error) => setError(exception.message))
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        loadRecord();
    }, [resource, id]);

    async function resend() {
        if (!definition.endpoints.resend) return;
        if (!window.confirm(`Resend merchant callback for order ${record?.reference}?`)) {
            return;
        }

        try {
            await ResourceService.resendCallback(definition.endpoints.resend(record.id));
            loadRecord();
        } catch (exception: any) {
            alert(exception.message || 'Failed to resend callback.');
        }
    }

    return {
        definition,
        record,
        loading,
        error,
        reload: loadRecord,
        resend,
    };
}
