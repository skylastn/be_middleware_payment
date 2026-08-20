import React from 'react';

export interface PanelHeaderProps {
    title: string;
    kicker?: string;
    aside?: React.ReactNode;
}

export function PanelHeader({ title, kicker, aside }: PanelHeaderProps): React.JSX.Element {
    return (
        <div className="panel-header">
            <div>
                <h3 className="panel-title">{title}</h3>
                {kicker && <div className="panel-kicker">{kicker}</div>}
            </div>
            {aside && <div className="panel-aside">{aside}</div>}
        </div>
    );
}
