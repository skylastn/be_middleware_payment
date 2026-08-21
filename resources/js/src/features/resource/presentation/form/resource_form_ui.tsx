import React from 'react';
import { ResourceKey } from '@/features/resource/domain/model/resource_model';
import { navigate } from '@/shared/utils/format_utils';
import { PageTitle } from '@/shared/component/ui/page_title';
import { Field } from '@/shared/component/ui/field';
import { CopyButton } from '@/shared/component/ui/copy_button';
import { IconArrowLeft } from '@/shared/component/ui/icons';
import { useResourceFormLogic } from './resource_form_logic';

export interface ResourceFormProps {
    resource: ResourceKey;
    id?: string;
}

export function ResourceForm({ resource, id }: ResourceFormProps): React.JSX.Element {
    const {
        definition,
        isEdit,
        values,
        setValues,
        readonlyValues,
        loadError,
        formError,
        submitting,
        submit,
    } = useResourceFormLogic({ resource, id });

    if (loadError) {
        return <div className="panel empty">{loadError}</div>;
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
                        onChange={(value: string) => setValues((current) => ({ ...current, [name]: value }))}
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
