import React from 'react';
import { FieldDefinition } from '@/features/resource/domain/model/resource_model';
import { title } from '@/shared/utils/format_utils';

export interface FieldProps {
    name: string;
    field: FieldDefinition;
    value: any;
    onChange?: (value: string) => void;
    readonly?: boolean;
}

export function Field({ name, field, value, onChange = () => {}, readonly = false }: FieldProps): React.JSX.Element {
    const type = field.type || 'text';
    const label = field.label || title(name);
    const className = `field ${['textarea', 'json'].includes(type) ? 'full' : ''}`;
    const stringValue = typeof value === 'object' && value !== null ? JSON.stringify(value, null, 2) : String(value ?? '');

    const formatJson = () => {
        try {
            const parsed = JSON.parse(stringValue);
            onChange(JSON.stringify(parsed, null, 2));
        } catch {
            // keep raw on syntax error
        }
    };

    return (
        <label className={className}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <span className="label">{label}</span>
                {type === 'json' && !readonly && (
                    <button type="button" className="button" style={{ minHeight: '26px', padding: '2px 8px', fontSize: '11px' }} onClick={formatJson}>
                        Format JSON
                    </button>
                )}
            </div>
            {type === 'select' ? (
                <select
                    className="input"
                    value={value || ''}
                    disabled={readonly}
                    required={field.required}
                    onChange={(event) => onChange(event.target.value)}
                >
                    {(field.options || []).map((option: string) => (
                        <option value={option} key={option}>
                            {option}
                        </option>
                    ))}
                </select>
            ) : ['textarea', 'json'].includes(type) ? (
                <textarea
                    className="input mono"
                    rows={type === 'json' ? 9 : 4}
                    value={stringValue}
                    readOnly={readonly}
                    required={field.required}
                    placeholder={type === 'json' ? '{\n  "key": "value"\n}' : ''}
                    onChange={(event) => onChange(event.target.value)}
                />
            ) : (
                <input
                    className={readonly ? 'input mono' : 'input'}
                    value={stringValue}
                    readOnly={readonly}
                    required={field.required}
                    onChange={(event) => onChange(event.target.value)}
                />
            )}
        </label>
    );
}
