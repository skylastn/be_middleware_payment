import React from 'react';
import { PageTitle } from '@/shared/component/ui/page_title';
import { StatCard } from '@/shared/component/ui/stat_card';
import { PanelHeader } from '@/shared/component/ui/panel_header';
import { DataTable } from '@/shared/component/ui/data_table';
import { CopyButton } from '@/shared/component/ui/copy_button';
import { IconCalendar, IconRefresh, IconRepositories, IconX } from '@/shared/component/ui/icons';
import { getMonthRange, getLastDaysRange, getTodayDateString } from '@/shared/utils/format_utils';
import { Skeleton, SkeletonDashboard, SkeletonStatsGrid, SkeletonTableRows } from '@/shared/component/ui/skeleton';
import { useDashboardLogic } from './dashboard_logic';

export function DashboardPage(): React.JSX.Element {
    const {
        data,
        error,
        loading,
        startDate,
        setStartDate,
        endDate,
        setEndDate,
        selectedRepository,
        setSelectedRepository,
        handleClearFilters,
        reload,
    } = useDashboardLogic();

    if (error) {
        return <div className="panel empty">{error}</div>;
    }

    if (!data && loading) {
        return (
            <>
                <PageTitle
                    eyebrow="Live Overview"
                    title="Payment Monitoring Dashboard"
                    subtitle="Real-time operational metrics for orders, projects, and gateway transactions."
                />
                <SkeletonDashboard />
            </>
        );
    }

    if (!data) {
        return <div className="panel empty">No dashboard data available.</div>;
    }

    const maxMode = Math.max(...(data.modeCounts || []).map((mode) => Number(mode.total || 0)), 1);

    const applyPreset = (preset: 'today' | '7days' | 'month' | '30days') => {
        if (preset === 'today') {
            const today = getTodayDateString();
            setStartDate(today);
            setEndDate(today);
        } else if (preset === '7days') {
            const { startDate: s, endDate: e } = getLastDaysRange(7);
            setStartDate(s);
            setEndDate(e);
        } else if (preset === '30days') {
            const { startDate: s, endDate: e } = getLastDaysRange(30);
            setStartDate(s);
            setEndDate(e);
        } else if (preset === 'month') {
            const { startDate: s, endDate: e } = getMonthRange();
            setStartDate(s);
            setEndDate(e);
        }
    };

    const isMonthActive = (() => {
        const { startDate: s, endDate: e } = getMonthRange();
        return startDate === s && endDate === e;
    })();

    const isTodayActive = (() => {
        const today = getTodayDateString();
        return startDate === today && endDate === today;
    })();

    const is7DaysActive = (() => {
        const { startDate: s, endDate: e } = getLastDaysRange(7);
        return startDate === s && endDate === e;
    })();

    const is30DaysActive = (() => {
        const { startDate: s, endDate: e } = getLastDaysRange(30);
        return startDate === s && endDate === e;
    })();

    const hasActiveFilters = Boolean(
        (selectedRepository && selectedRepository !== 'all') ||
        (!isMonthActive && (startDate || endDate))
    );

    return (
        <>
            <PageTitle
                eyebrow="Live Overview"
                title="Payment Monitoring Dashboard"
                subtitle="Real-time operational metrics for orders, projects, and gateway transactions."
            >
                <div className="toolbar">
                    <span className="filter-badge">Updated {data.updatedAt}</span>
                    <button
                        type="button"
                        className="button"
                        onClick={reload}
                        disabled={loading}
                        title="Refresh metrics"
                    >
                        <IconRefresh /> {loading ? 'Refreshing...' : 'Refresh'}
                    </button>
                </div>
            </PageTitle>

            <div className="modern-filter-panel">
                <div className="modern-filter-row">
                    <div className="modern-filter-fields">
                        <div className="modern-input-group">
                            <span className="input-icon"><IconCalendar /></span>
                            <label>From</label>
                            <input
                                type="date"
                                value={startDate}
                                onChange={(e) => setStartDate(e.target.value)}
                            />
                        </div>

                        <div className="modern-input-group">
                            <span className="input-icon"><IconCalendar /></span>
                            <label>To</label>
                            <input
                                type="date"
                                value={endDate}
                                onChange={(e) => setEndDate(e.target.value)}
                            />
                        </div>

                        <div className="modern-input-group">
                            <span className="input-icon"><IconRepositories /></span>
                            <label>Repo</label>
                            <select
                                value={selectedRepository}
                                onChange={(e) => setSelectedRepository(e.target.value)}
                            >
                                <option value="all">All Repositories</option>
                                {(data.repositories || []).map((repo) => (
                                    <option key={repo.id} value={repo.id}>
                                        {repo.gateway} ({repo.mode}) - #{repo.id}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>

                    <div className="modern-filter-chips">
                        <span
                            className={`filter-chip ${isTodayActive ? 'active' : ''}`}
                            onClick={() => applyPreset('today')}
                        >
                            Today
                        </span>
                        <span
                            className={`filter-chip ${is7DaysActive ? 'active' : ''}`}
                            onClick={() => applyPreset('7days')}
                        >
                            Last 7 Days
                        </span>
                        <span
                            className={`filter-chip ${isMonthActive ? 'active' : ''}`}
                            onClick={() => applyPreset('month')}
                        >
                            This Month
                        </span>
                        <span
                            className={`filter-chip ${is30DaysActive ? 'active' : ''}`}
                            onClick={() => applyPreset('30days')}
                        >
                            Last 30 Days
                        </span>
                        {hasActiveFilters && (
                            <button
                                type="button"
                                className="filter-chip"
                                onClick={handleClearFilters}
                                style={{ background: 'var(--danger-light)', color: 'var(--danger)', borderColor: 'var(--danger)' }}
                                title="Reset filter to default month"
                            >
                                <IconX /> Reset
                            </button>
                        )}
                    </div>
                </div>
            </div>

            {loading ? (
                <div style={{ display: 'flex', flexDirection: 'column', gap: '24px' }}>
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
            ) : (
                <>
                    <section className="grid stats">
                        <StatCard label="Total Orders" value={data.summary.orders} note="Captured transactions" tone="blue" />
                        <StatCard label="Success" value={data.summary.paidOrders} note="Completed payments" />
                        <StatCard label="Pending" value={data.summary.pendingOrders} note="Awaiting callback" tone="warning" />
                        <StatCard label="Failed / Expired" value={data.summary.failedOrders} note="Failed / canceled" tone="danger" />
                    </section>

                    <section className="grid stats">
                        <StatCard label="Projects" value={data.summary.projects} note="Registered merchant apps" />
                        <StatCard label="Gateways" value={data.summary.paymentGateways} note="Supported gateways" tone="purple" />
                        <StatCard label="Repositories" value={data.summary.paymentRepositories} note="Credential sets" tone="blue" />
                        <StatCard label="Methods" value={data.summary.paymentMethods} note="Payment channels" />
                    </section>

                    <section className="chart-grid">
                        <div className="panel">
                            <PanelHeader title="Status Distribution" kicker="Success, pending, and failed ratio" />
                            <div
                                className="donut"
                                style={
                                    {
                                        '--success-deg': `${data.statusMix?.successDeg || 0}deg`,
                                        '--pending-deg': `${data.statusMix?.pendingDeg || 0}deg`,
                                    } as React.CSSProperties
                                }
                            />
                            <div className="list">
                                <div className="list-row">
                                    <span className="list-title">Success</span>
                                    <strong>{Number(data.statusMix?.success || 0).toLocaleString()}</strong>
                                </div>
                                <div className="list-row">
                                    <span className="list-title">Pending</span>
                                    <strong>{Number(data.statusMix?.pending || 0).toLocaleString()}</strong>
                                </div>
                                <div className="list-row">
                                    <span className="list-title">Failed / Expired</span>
                                    <strong>{Number(data.statusMix?.failedExpired || 0).toLocaleString()}</strong>
                                </div>
                            </div>
                        </div>

                        <div className="panel">
                            <PanelHeader title="Environment Traffic" kicker="Transaction volume by mode" />
                            <div className="bar-list">
                                {(data.modeCounts || []).map((mode) => (
                                    <div className="bar-row" key={mode.mode}>
                                        <span className="list-title">{mode.mode.toUpperCase()}</span>
                                        <span className="bar-track">
                                            <span
                                                className={`bar-fill ${mode.mode === 'prod' ? 'blue' : 'warning'}`}
                                                style={{
                                                    width: `${Math.max(
                                                        4,
                                                        Math.round((Number(mode.total || 0) / maxMode) * 100)
                                                    )}%`,
                                                }}
                                            />
                                        </span>
                                        <strong>{Number(mode.total || 0).toLocaleString()}</strong>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section className="panel" style={{ marginTop: '24px' }}>
                        <PanelHeader
                            title="Recent Orders"
                            kicker="Latest payment requests matching active filters"
                            aside={`${data.recentOrders?.length || 0} records`}
                        />
                        <DataTable columns={['Reference', 'Project', 'Method', 'Status', 'Mode', 'Created']}>
                            {(data.recentOrders || []).length > 0 ? (
                                data.recentOrders.map((order) => (
                                    <tr key={order.reference}>
                                        <td className="mono">
                                            {order.reference}
                                            <CopyButton text={order.reference} />
                                        </td>
                                        <td>{order.type}</td>
                                        <td>{order.paymentMethod || '-'}</td>
                                        <td>
                                            <span className={`badge ${order.statusClass}`}>{order.status}</span>
                                        </td>
                                        <td>
                                            <span className={`badge ${order.mode === 'prod' ? 'success' : 'blue'}`}>
                                                {order.mode}
                                            </span>
                                        </td>
                                        <td>{order.createdAt || '-'}</td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td className="empty" colSpan={6} style={{ padding: '36px', textAlign: 'center' }}>
                                        No recent orders recorded.
                                    </td>
                                </tr>
                            )}
                        </DataTable>
                    </section>
                </>
            )}
        </>
    );
}
