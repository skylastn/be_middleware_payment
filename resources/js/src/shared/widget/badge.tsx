import React from 'react';

export function renderBadge(column: string, val: any): React.JSX.Element {
    const str = String(val || '').toUpperCase();
    if (column === 'status') {
        if (str === 'SUCCESS' || str === 'PAID' || str === '00') {
            return <span className="badge success">{str}</span>;
        }
        if (str === 'PENDING' || str === 'OPEN' || str === 'WAITING') {
            return <span className="badge warning">{str}</span>;
        }
        if (str === 'FAILED' || str === 'EXPIRED' || str === 'CANCEL') {
            return <span className="badge danger">{str}</span>;
        }
    }
    if (column === 'mode') {
        if (str === 'PROD' || str === 'PRODUCTION') {
            return <span className="badge success">PROD</span>;
        }
        return <span className="badge blue">SANDBOX</span>;
    }
    return <span>{String(val ?? '-')}</span>;
}

export function renderLogLevelBadge(level: string): React.JSX.Element {
    const lvl = String(level || '').toUpperCase();
    if (['EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR'].includes(lvl)) {
        return <span className="badge danger">{lvl}</span>;
    }
    if (['WARNING', 'WARN'].includes(lvl)) {
        return <span className="badge warning">{lvl}</span>;
    }
    if (['INFO', 'NOTICE'].includes(lvl)) {
        return <span className="badge blue">{lvl}</span>;
    }
    if (['DEBUG'].includes(lvl)) {
        return <span className="badge">{lvl}</span>;
    }
    return <span className="badge">{lvl}</span>;
}
