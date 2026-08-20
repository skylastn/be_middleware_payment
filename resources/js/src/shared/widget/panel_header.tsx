import React from 'react';

export interface PanelHeaderProps {
    title: string;
    kicker: string;
    aside?: React.ReactNode;
}

export function PanelHeader({ title, kicker, aside }: PanelHeaderProps): React.JSX.Element {
    return (
        <div className="panel-header">
            <div>
                <h2 className="panel-title">{title}</h2>
                <div className="panel-kicker">{kicker}</div>
            </div>
            {aside && (typeof aside === 'string' ? <span className="filter-badge">{aside}</span> : aside)}
        </div>
    );
}
