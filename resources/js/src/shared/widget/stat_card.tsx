import React from 'react';

export interface StatCardProps {
    label: string;
    value?: number | string | null;
    note: string;
    tone?: string;
}

export function StatCard({ label, value, note, tone = '' }: StatCardProps): React.JSX.Element {
    return (
        <div className={`panel stat ${tone}`}>
            <div className="stat-label">{label}</div>
            <div className="stat-value">{Number(value || 0).toLocaleString()}</div>
            <div className="stat-note">{note}</div>
        </div>
    );
}
