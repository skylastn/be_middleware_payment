import React, { useEffect, useState } from 'react';
import { ResourceKey } from '@/features/resource/domain/model/resource_model';
import { resourceDefinitions } from '@/features/resource/domain/constant/resource_definitions';
import { ResourceService } from '@/features/resource/application/resource_service';
import { dataRecord, navigate, title } from '@/shared/utils/format_utils';

export interface UseResourceFormLogicProps {
    resource: ResourceKey;
    id?: string;
}

export function useResourceFormLogic({ resource, id }: UseResourceFormLogicProps) {
    const definition = resourceDefinitions[resource];
    const isEdit = Boolean(id);
    const [values, setValues] = useState<Record<string, any>>({});
    const [readonlyValues, setReadonlyValues] = useState<Record<string, any>>({});
    const [loadError, setLoadError] = useState<string>('');
    const [formError, setFormError] = useState<string>('');
    const [submitting, setSubmitting] = useState<boolean>(false);

    useEffect(() => {
        setLoadError('');
        setFormError('');
        setValues({});
        setReadonlyValues({});
        if (!isEdit || !id) {
            return;
        }

        ResourceService.show(definition.endpoints.show!(id))
            .then((payload) => {
                const record = dataRecord(payload);
                const formatted = { ...(record || {}) };
                for (const [name, field] of Object.entries(definition.fields || {})) {
                    if (field.type === 'json' && formatted[name] && typeof formatted[name] === 'object') {
                        formatted[name] = JSON.stringify(formatted[name], null, 2);
                    }
                }
                setValues(formatted);
                setReadonlyValues(record || {});
            })
            .catch((exception: Error) => setLoadError(exception.message));
    }, [resource, id, isEdit]);

    async function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setFormError('');

        const payloadValues = { ...values };

        // Strict Client-side JSON & Field Validation
        for (const [name, field] of Object.entries(definition.fields || {})) {
            if (field.type === 'json') {
                const raw = payloadValues[name];
                const fieldLabel = field.label || title(name);

                if (raw === undefined || raw === null || (typeof raw === 'string' && !raw.trim())) {
                    if (field.required) {
                        setFormError(`Field "${fieldLabel}" is required.`);
                        return;
                    }
                    payloadValues[name] = {};
                    continue;
                }

                if (typeof raw === 'string') {
                    let parsed: any;
                    try {
                        parsed = JSON.parse(raw);
                    } catch (parseError: any) {
                        setFormError(`Invalid JSON syntax in "${fieldLabel}": ${parseError?.message || 'Syntax error'}`);
                        return;
                    }

                    if (typeof parsed !== 'object' || parsed === null || Array.isArray(parsed)) {
                        setFormError(`Field "${fieldLabel}" must be a valid JSON object (e.g. {"key": "value"}).`);
                        return;
                    }

                    payloadValues[name] = parsed;
                }
            }
        }

        const targetUrl = isEdit && id && definition.endpoints.update
            ? definition.endpoints.update(id)
            : definition.endpoints.create;

        if (!targetUrl) return;

        setSubmitting(true);
        try {
            if (isEdit) {
                await ResourceService.update(targetUrl, payloadValues);
            } else {
                await ResourceService.create(targetUrl, payloadValues);
            }
            navigate(`/admin/${resource}`);
        } catch (exception: any) {
            setFormError(exception.message || 'Failed to save record.');
        } finally {
            setSubmitting(false);
        }
    }

    return {
        definition,
        isEdit,
        values,
        setValues,
        readonlyValues,
        loadError,
        formError,
        submitting,
        submit,
    };
}
