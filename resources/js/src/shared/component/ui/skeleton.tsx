import React from 'react';

export interface SkeletonProps {
    width?: string | number;
    height?: string | number;
    borderRadius?: string | number;
    className?: string;
    style?: React.CSSProperties;
}

export function Skeleton({
    width = '100%',
    height = '16px',
    borderRadius = 'var(--radius-sm)',
    className = '',
    style = {},
}: SkeletonProps): React.JSX.Element {
    return (
        <span
            className={`shimmer ${className}`}
            style={{
                width: typeof width === 'number' ? `${width}px` : width,
                height: typeof height === 'number' ? `${height}px` : height,
                borderRadius: typeof borderRadius === 'number' ? `${borderRadius}px` : borderRadius,
                ...style,
            }}
        />
    );
}

export function SkeletonStatsGrid({ count = 4 }: { count?: number }): React.JSX.Element {
    return (
        <section className="grid stats">
            {Array.from({ length: count }).map((_, idx) => (
                <div className="skeleton-stat-card" key={idx}>
                    <Skeleton width="45%" height="13px" />
                    <Skeleton width="70%" height="32px" borderRadius="var(--radius-md)" />
                    <Skeleton width="55%" height="11px" />
                </div>
            ))}
        </section>
    );
}

export function SkeletonTableRows({
    rows = 5,
    columns = 6,
}: {
    rows?: number;
    columns?: number;
}): React.JSX.Element {
    return (
        <>
            {Array.from({ length: rows }).map((_, rIdx) => (
                <tr key={rIdx}>
                    {Array.from({ length: columns }).map((_, cIdx) => (
                        <td key={cIdx}>
                            <Skeleton
                                width={cIdx === 0 ? '80%' : cIdx === columns - 1 ? '50%' : '65%'}
                                height="16px"
                            />
                        </td>
                    ))}
                </tr>
            ))}
        </>
    );
}

export function SkeletonFormFields({ count = 6 }: { count?: number }): React.JSX.Element {
    return (
        <div className="panel form-grid" style={{ marginBottom: '24px' }}>
            {Array.from({ length: count }).map((_, idx) => (
                <div className={`field ${idx === 0 || idx === count - 1 ? 'full' : ''}`} key={idx}>
                    <Skeleton width="100px" height="13px" />
                    <Skeleton width="100%" height="38px" borderRadius="var(--radius-md)" />
                </div>
            ))}
        </div>
    );
}

export function SkeletonDashboard(): React.JSX.Element {
    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '24px' }}>
            <div className="skeleton-stat-card" style={{ padding: '16px 20px', gap: '10px' }}>
                <Skeleton width="160px" height="14px" />
                <div style={{ display: 'flex', gap: '12px', flexWrap: 'wrap' }}>
                    <Skeleton width="180px" height="38px" borderRadius="var(--radius-md)" />
                    <Skeleton width="180px" height="38px" borderRadius="var(--radius-md)" />
                    <Skeleton width="220px" height="38px" borderRadius="var(--radius-md)" />
                </div>
            </div>

            <SkeletonStatsGrid count={4} />
            <SkeletonStatsGrid count={4} />

            <div className="chart-grid">
                <div className="panel" style={{ padding: '24px', display: 'flex', flexDirection: 'column', gap: '16px' }}>
                    <Skeleton width="140px" height="16px" />
                    <div style={{ display: 'flex', justifyContent: 'center', padding: '20px 0' }}>
                        <Skeleton width="160px" height="160px" borderRadius="50%" />
                    </div>
                </div>
                <div className="panel" style={{ padding: '24px', display: 'flex', flexDirection: 'column', gap: '16px' }}>
                    <Skeleton width="140px" height="16px" />
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '12px', paddingTop: '10px' }}>
                        <Skeleton width="100%" height="24px" />
                        <Skeleton width="85%" height="24px" />
                        <Skeleton width="70%" height="24px" />
                    </div>
                </div>
            </div>

            <div className="panel">
                <div style={{ padding: '20px', borderBottom: '1px solid var(--border)' }}>
                    <Skeleton width="180px" height="18px" />
                </div>
                <table className="table" style={{ width: '100%' }}>
                    <tbody>
                        <SkeletonTableRows rows={6} columns={6} />
                    </tbody>
                </table>
            </div>
        </div>
    );
}
