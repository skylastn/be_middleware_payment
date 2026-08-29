import React from 'react';

export interface PageTitleProps {
    eyebrow: string;
    title: string;
    subtitle: string;
    children?: React.ReactNode;
}

export function PageTitle({ eyebrow, title, subtitle, children }: PageTitleProps): React.JSX.Element {
    return (
        <div className="page-title">
            <div>
                <div className="eyebrow">{eyebrow}</div>
                <h1>{title}</h1>
                <div className="subtitle">{subtitle}</div>
            </div>
            {children && <div className="toolbar">{children}</div>}
        </div>
    );
}
