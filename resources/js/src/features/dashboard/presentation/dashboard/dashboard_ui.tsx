import React from 'react';
import { PageTitle } from '@/shared/component/ui/page_title';
import { StatCard } from '@/shared/component/ui/stat_card';
import { PanelHeader } from '@/shared/component/ui/panel_header';
import { DataTable } from '@/shared/component/ui/data_table';
import { CopyButton } from '@/shared/component/ui/copy_button';
import { useDashboardLogic } from './dashboard_logic';

export function DashboardPage(): React.JSX.Element {
    const { data, error, loading, reload } = useDashboardLogic();

    if (error) {
        return <div className="panel empty">{error}</div>;
    }

    if (!data) {
        return <div className="panel empty">Loading dashboard metrics...</div>;
    }

    const maxMode = Math.max(...(data.modeCounts || []).map((mode) => Number(mode.total || 0)), 1);

    return (
        <>
            <PageTitle
                eyebrow="Live Overview"
                title="Payment Monitoring Dashboard"
                subtitle="Real-time operational metrics for orders, projects, and gateway transactions."
            >
                <span className="filter-badge">Updated {data.updatedAt}</span>
            </PageTitle>

            <section className="grid stats">
                <StatCard label="Total Orders" value={data.summary.orders} note="All captured transactions" tone="blue" />
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

            <section className="grid columns">
                <div className="panel">
                    <PanelHeader
                        title="Recent Orders"
                        kicker="Latest payment requests"
                        aside={`${data.recentOrders?.length || 0} latest`}
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
                                <td className="empty" colSpan={6}>
                                    No recent orders recorded.
                                </td>
                            </tr>
                        )}
                    </DataTable>
                </div>

                <div className="panel">
                    <PanelHeader title="Active Repositories" kicker="Gateway credentials by mode" />
                    <div className="list">
                        {(data.repositories || []).length > 0 ? (
                            data.repositories.map((repository) => (
                                <div className="list-row" key={repository.id}>
                                    <div className="list-main">
                                        <span className="list-title">{repository.gateway}</span>
                                        <div className="muted mono" style={{ fontSize: '11px' }}>
                                            {repository.id}
                                            <CopyButton text={repository.id} />
                                        </div>
                                    </div>
                                    <span className={`badge ${repository.mode === 'prod' ? 'success' : 'blue'}`}>
                                        {repository.mode}
                                    </span>
                                </div>
                            ))
                        ) : (
                            <div className="empty" style={{ padding: '24px', textAlign: 'center' }}>
                                No active repositories.
                            </div>
                        )}
                    </div>
                </div>
            </section>
        </>
    );
}
