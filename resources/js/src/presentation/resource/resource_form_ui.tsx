import React, { useEffect, useState } from 'react';
import { ResourceKey } from '../../model/resource_model';
import { resourceDefinitions } from '../../shared/constant/resource_definitions';
import { ResourceService } from '../../services/resource_service';
import { dataRecord, navigate, title } from '../../shared/utils/format_utils';
import { PageTitle } from '../../shared/widget/page_title';
import { Field } from '../../shared/widget/field';
import { CopyButton } from '../../shared/widget/copy_button';
import { IconArrowLeft } from '../../shared/widget/icons';

export interface ResourceFormProps {
    resource: ResourceKey;
    id?: string;
}

export function ResourceForm({ resource, id }: ResourceFormProps): React.JSX.Element {
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

        ResourceService.show(definition.endpoints.show(id))
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

    if (loadError) {
        return <div className="panel empty">{loadError}</div>;
    }

    async function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setFormError('');

        const payloadValues = { ...values };

        // 1. Strict Client-side JSON & Field Validation
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

    return (
        <>
            <PageTitle
                eyebrow="Configuration"
                title={`${isEdit ? 'Edit' : 'Create'} ${definition.singular}`}
                subtitle={`Configure ${definition.singular.toLowerCase()} parameters and details.`}
            >
                <button className="button" onClick={() => navigate(`/admin/${resource}`)}>
                    <IconArrowLeft /> Back to List
                </button>
            </PageTitle>

            <form className="panel form-grid" onSubmit={submit}>
                {formError && (
                    <div className="field full">
                        <div className="alert">{formError}</div>
                    </div>
                )}

                {/* ID / Primary Key (Disabled & Readonly) */}
                <label className="field full">
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                        <span className="label">ID (Primary Key)</span>
                        {isEdit && id && <CopyButton text={id} />}
                    </div>
                    <input
                        className="input mono"
                        type="text"
                        value={isEdit ? (id || values.id || '') : '(Auto-generated on save)'}
                        disabled
                        readOnly
                        style={{
                            cursor: 'not-allowed',
                            background: 'var(--surface-subtle)',
                            opacity: 0.85,
                            fontStyle: isEdit ? 'normal' : 'italic',
                        }}
                    />
                </label>

                {Object.entries(definition.fields).map(([name, field]) => (
                    <Field
                        key={name}
                        name={name}
                        field={field}
                        value={values[name] ?? field.default ?? ''}
                        onChange={(value) => setValues((current) => ({ ...current, [name]: value }))}
                    />
                ))}

                {isEdit &&
                    Object.entries(definition.readonlyFields || {}).map(([name, field]) => (
                        <Field key={name} name={name} field={field} value={readonlyValues[name] ?? ''} readonly />
                    ))}

                <div className="field full">
                    <button className="button primary" type="submit" disabled={submitting}>
                        {submitting ? 'Saving...' : isEdit ? 'Save Changes' : 'Create Record'}
                    </button>
                </div>
            </form>
        </>
    );
}
