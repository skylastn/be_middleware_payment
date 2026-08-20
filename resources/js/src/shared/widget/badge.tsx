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
