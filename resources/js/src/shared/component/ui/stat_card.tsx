import React from 'react';

export interface StatCardProps {
    label: string;
    value?: number | string | null;
    note?: string;
    sub?: string;
    tone?: string;
    icon?: React.ReactNode;
}

export function StatCard({ label, value, note, sub, tone = '', icon }: StatCardProps): React.JSX.Element {
    return (
        <div className={`panel stat ${tone}`}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <div className="stat-label">{label}</div>
                {icon && <span className="stat-icon" style={{ color: 'var(--text-subtle)' }}>{icon}</span>}
            </div>
            <div className="stat-value">{Number(value || 0).toLocaleString()}</div>
            {(note || sub) && <div className="stat-note">{note || sub}</div>}
        </div>
    );
}
