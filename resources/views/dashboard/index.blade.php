@extends('layouts.dashboard', ['title' => 'Dashboard'])

@section('content')
    <div class="page-title">
        <div>
            <div class="eyebrow">Live overview</div>
            <h1>Payment Monitoring</h1>
            <div class="subtitle">Operational snapshot for orders, projects, and payment gateway configuration.</div>
        </div>
        <div class="toolbar">
            <span class="timestamp">Updated {{ now()->format('Y-m-d H:i') }}</span>
        </div>
    </div>

    <section class="grid stats">
        <div class="panel stat blue">
            <div class="stat-label">Total Orders</div>
            <div class="stat-value">{{ number_format($summary['orders']) }}</div>
            <div class="stat-note">All captured payment orders</div>
        </div>
        <div class="panel stat">
            <div class="stat-label">Success</div>
            <div class="stat-value">{{ number_format($summary['paidOrders']) }}</div>
            <div class="stat-note">Orders marked as successful</div>
        </div>
        <div class="panel stat warning">
            <div class="stat-label">Pending</div>
            <div class="stat-value">{{ number_format($summary['pendingOrders']) }}</div>
            <div class="stat-note">Waiting for callback or payment</div>
        </div>
        <div class="panel stat danger">
            <div class="stat-label">Failed / Expired</div>
            <div class="stat-value">{{ number_format($summary['failedOrders']) }}</div>
            <div class="stat-note">Failed and expired payments</div>
        </div>
    </section>

    <section class="grid stats">
        <div class="panel stat stat-mini">
            <div class="stat-label">Projects</div>
            <div class="stat-value">{{ number_format($summary['projects']) }}</div>
            <div class="stat-note">Registered clients</div>
        </div>
        <div class="panel stat stat-mini purple">
            <div class="stat-label">Gateways</div>
            <div class="stat-value">{{ number_format($summary['paymentGateways']) }}</div>
            <div class="stat-note">Gateway providers</div>
        </div>
        <div class="panel stat stat-mini blue">
            <div class="stat-label">Repositories</div>
            <div class="stat-value">{{ number_format($summary['paymentRepositories']) }}</div>
            <div class="stat-note">Credential sets</div>
        </div>
        <div class="panel stat stat-mini">
            <div class="stat-label">Methods</div>
            <div class="stat-value">{{ number_format($summary['paymentMethods']) }}</div>
            <div class="stat-note">Available methods</div>
        </div>
    </section>

    <section class="grid columns">
        <div class="panel">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Recent Orders</h2>
                    <div class="panel-kicker">Latest payment activity</div>
                </div>
                <span class="muted">{{ $recentOrders->count() }} latest</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Project</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Mode</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentOrders as $order)
                            <tr>
                                <td class="mono">{{ $order->reference }}</td>
                                <td>{{ $order->type }}</td>
                                <td>{{ $order->payment_method ?: '-' }}</td>
                                <td>
                                    @php
                                        $orderStatus = $order->status instanceof \App\Enums\OrderStatus ? $order->status : \App\Enums\OrderStatus::fromName($order->status);
                                        $status = $orderStatus?->value ?? \App\Enums\OrderStatus::PENDING->value;
                                        $statusClass = $orderStatus?->isSuccess() ? 'success' : ($orderStatus?->isFailed() ? 'danger' : 'warning');
                                    @endphp
                                    <span class="badge {{ $statusClass }}">{{ $status }}</span>
                                </td>
                                <td>{{ $order->mode instanceof \App\Enums\PaymentModeType ? $order->mode->value : $order->mode }}</td>
                                <td>{{ $order->created_at?->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="empty">No orders yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid">
            <div class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Order Status</h2>
                        <div class="panel-kicker">Grouped by current state</div>
                    </div>
                </div>
                <div class="list">
                    @forelse ($statusCounts as $status => $total)
                        <div class="list-row">
                            <span class="list-title">{{ $status }}</span>
                            <strong>{{ number_format($total) }}</strong>
                        </div>
                    @empty
                        <div class="empty">No status data yet.</div>
                    @endforelse
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Order Modes</h2>
                        <div class="panel-kicker">Sandbox and production split</div>
                    </div>
                </div>
                <div class="list">
                    @forelse ($modeCounts as $mode)
                        <div class="list-row">
                            <span class="list-title">{{ $mode->mode ?: 'UNKNOWN' }}</span>
                            <strong>{{ number_format($mode->total) }}</strong>
                        </div>
                    @empty
                        <div class="empty">No mode data yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>

    <section class="grid columns">
        <div class="panel">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Projects</h2>
                    <div class="panel-kicker">Configured project callbacks</div>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Gateway</th>
                            <th>Callback</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($projects as $project)
                            @php
                                $projectSlug = $project->slug instanceof \App\Enums\ProjectSlug ? $project->slug->value : $project->slug;
                            @endphp
                            <tr>
                                <td>{{ $project->name }}</td>
                                <td>{{ $project->type }}</td>
                                <td>{{ $projectSlug }}</td>
                                <td class="mono">{{ $project->callback }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty">No projects configured.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Payment Repositories</h2>
                    <div class="panel-kicker">Gateway credentials by mode</div>
                </div>
            </div>
            <div class="list">
                @forelse ($repositories as $repository)
                    @php
                        $repositoryMode = $repository->mode instanceof \App\Enums\PaymentModeType ? $repository->mode->value : $repository->mode;
                    @endphp
                    <div class="list-row">
                        <div class="list-main">
                            <span class="list-title">{{ $repository->payment_gateway?->name ?? 'Unknown gateway' }}</span>
                            <div class="muted mono">{{ $repository->id }}</div>
                        </div>
                        <span class="badge">{{ $repositoryMode }}</span>
                    </div>
                @empty
                    <div class="empty">No repository configuration yet.</div>
                @endforelse
            </div>
        </div>
    </section>
@endsection
