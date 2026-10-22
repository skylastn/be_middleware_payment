import React from 'react';
import { PageTitle } from '@/shared/component/ui/page_title';
import { renderBadge } from '@/shared/component/ui/badge';
import { CopyButton } from '@/shared/component/ui/copy_button';
import { IconRefresh, IconArrowLeft, IconCheck, IconBeaker } from '@/shared/component/ui/icons';
import { formatDate, formatCurrency, navigate } from '@/shared/utils/format_utils';
import { useUatTestLogic } from './uat_bank_agi_logic';

interface ScenarioRow {
    no: number;
    name: string;
    category: 'auth' | 'validation' | 'lifecycle' | 'edge';
    categoryLabel: string;
    expectedCode: string;
    how: string;
    action: 'negative' | 'generate' | 'query' | 'notify' | 'manual' | 'blocked';
    case?: string;
    amount?: number;
    expiry?: number;
    notifyStatus?: string;
}

const SCENARIOS: ScenarioRow[] = [
    { no: 1, name: 'Access Token Invalid', category: 'auth', categoryLabel: 'Auth', expectedCode: '4011901', how: 'Notify with an unverified / invalid B2B bearer token', action: 'negative', case: 'invalid_token' },
    { no: 2, name: 'Unauthorized Signature', category: 'auth', categoryLabel: 'Auth', expectedCode: '4011900', how: 'Notify with a corrupted HMAC-SHA512 signature', action: 'negative', case: 'invalid_signature' },
    { no: 3, name: 'Missing Mandatory Field', category: 'validation', categoryLabel: 'Validation', expectedCode: '4001902', how: 'Notify missing mandatory merchantUser & billNumber', action: 'negative', case: 'missing_mandatory' },
    { no: 4, name: 'Invalid Field Format', category: 'validation', categoryLabel: 'Validation', expectedCode: '4001901', how: 'Notify with latestTransactionStatus = 99 (invalid enum)', action: 'negative', case: 'invalid_format' },
    { no: 5, name: 'Duplicate X-EXTERNAL-ID', category: 'validation', categoryLabel: 'Validation', expectedCode: '4091900', how: 'Reused external ID — requires middleware deduplication', action: 'blocked' },
    { no: 6, name: 'Show QR Code', category: 'lifecycle', categoryLabel: 'Lifecycle', expectedCode: '2001700', how: 'Generate dynamic QRIS with amount Rp 10.000', action: 'generate', amount: 10000 },
    { no: 7, name: 'Invalid Merchant', category: 'edge', categoryLabel: 'Edge Case', expectedCode: '4041708', how: 'Switch repository to unregistered merchant, then generate QR', action: 'manual' },
    { no: 8, name: 'QR Expired', category: 'lifecycle', categoryLabel: 'Lifecycle', expectedCode: '4041801', how: 'Generate QR with 1-min expiry, wait >60s, then query status', action: 'generate', amount: 10000, expiry: 1 },
    { no: 9, name: 'Query Pending', category: 'lifecycle', categoryLabel: 'Lifecycle', expectedCode: '4041801', how: 'Query newly generated QR before customer pays', action: 'query' },
    { no: 10, name: 'Query Failed', category: 'lifecycle', categoryLabel: 'Lifecycle', expectedCode: '4041801', how: 'Simulate failed notification (status 05), then query status', action: 'notify', notifyStatus: '05' },
    { no: 11, name: 'Notify Success', category: 'lifecycle', categoryLabel: 'Lifecycle', expectedCode: '2001900', how: 'Inbound notification simulation with status 00 (SUCCESS)', action: 'notify', notifyStatus: '00' },
    { no: 12, name: 'Query Success', category: 'lifecycle', categoryLabel: 'Lifecycle', expectedCode: '2001800', how: 'Query paid transaction to verify settlement status at bank', action: 'query' },
    { no: 13, name: 'Invalid Bill Number', category: 'validation', categoryLabel: 'Validation', expectedCode: '4041901', how: 'Notification attempt referencing an unknown bill number', action: 'negative', case: 'invalid_bill_number' },
    { no: 14, name: 'Nominal > 10.000.000', category: 'validation', categoryLabel: 'Validation', expectedCode: '4001701', how: 'Attempt QR generation exceeding Bank Indonesia QRIS limit', action: 'generate', amount: 10000001 },
    { no: 15, name: 'Insufficient Balance', category: 'edge', categoryLabel: 'Bank Side', expectedCode: '4041801', how: 'Requires physical bank test account with balance < transaction', action: 'manual' },
    { no: 16, name: 'Dormant Account', category: 'edge', categoryLabel: 'Bank Side', expectedCode: '4041801', how: 'Requires payer transaction using dormant account from bank', action: 'manual' },
    { no: 17, name: 'Post No Debit Account', category: 'edge', categoryLabel: 'Bank Side', expectedCode: '4041801', how: 'Requires payer transaction using Post No Debit account', action: 'manual' },
    { no: 18, name: 'Inactive Account', category: 'edge', categoryLabel: 'Bank Side', expectedCode: '4041801', how: 'Requires payer transaction using inactive account from bank', action: 'manual' },
];

export function UatBankAgiPage(): React.JSX.Element {
    const {
        repositories,
        selectedRepoId,
        setSelectedRepoId,
        selectedRepo,
        modeFilter,
        setModeFilter,
        loadingRepos,
        error,
        notice,
        amount,
        setAmount,
        expiryPeriod,
        setExpiryPeriod,
        qrResult,
        generating,
        handleGenerateQr,
        queryResult,
        querying,
        handleQueryStatus,
        notifyStatus,
        setNotifyStatus,
        notifyAmount,
        setNotifyAmount,
        notifyResult,
        notifying,
        handleNotify,
        negativeResult,
        negativeCase,
        runningNegative,
        handleNegativeTest,
        orders,
        loadingOrders,
        loadOrders,
    } = useUatTestLogic('bank_agi');

    return (
        <div className="page-container" style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
            <PageTitle
                eyebrow="UAT Test Suite"
                title="Bank Artha Graha (AGI) QRIS"
                subtitle="SNAP BI QR MPM On Us & Off Us test console. Run live bank sandbox inquiries, simulate verified callbacks, and validate 18 UAT test scenarios."
            >
                <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
                    <button
                        type="button"
                        className="button"
                        onClick={() => navigate('/admin/uat-testing')}
                        title="Back to Gateway Hub"
                    >
                        <IconArrowLeft /> Gateway Hub
                    </button>
                    <button
                        type="button"
                        className="button"
                        onClick={() => loadOrders()}
                        disabled={loadingOrders}
                        title="Refresh order history"
                    >
                        <IconRefresh /> {loadingOrders ? 'Refreshing...' : 'Refresh'}
                    </button>
                </div>
            </PageTitle>

            {error && (
                <div className="alert danger" style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                    <span>⚠️ {error}</span>
                </div>
            )}
            {notice && (
                <div className="alert" style={{ background: 'var(--success-light)', color: 'var(--success-text)', borderColor: 'rgba(16, 185, 129, 0.4)', display: 'flex', alignItems: 'center', gap: '8px' }}>
                    <IconCheck />
                    <span>{notice}</span>
                </div>
            )}

            {/* Active Repository & Environment Bar */}
            <div className="panel">
                <div className="uat-filter-strip">
                    <div style={{ display: 'flex', alignItems: 'center', gap: '14px', flexWrap: 'wrap' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                            <span style={{ fontSize: '11px', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.05em', color: 'var(--text-muted)' }}>
                                Environment:
                            </span>
                            <div className="modern-filter-chips">
                                <span
                                    className={`filter-chip ${modeFilter === 'sandbox' ? 'active' : ''}`}
                                    onClick={() => setModeFilter('sandbox')}
                                    style={{ cursor: 'pointer' }}
                                >
                                    Sandbox (Default)
                                </span>
                                <span
                                    className={`filter-chip ${modeFilter === 'prod' ? 'active' : ''}`}
                                    onClick={() => setModeFilter('prod')}
                                    style={{ cursor: 'pointer' }}
                                >
                                    Production
                                </span>
                                <span
                                    className={`filter-chip ${modeFilter === 'all' ? 'active' : ''}`}
                                    onClick={() => setModeFilter('all')}
                                    style={{ cursor: 'pointer' }}
                                >
                                    All Modes
                                </span>
                            </div>
                        </div>

                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                            <span style={{ fontSize: '11px', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.05em', color: 'var(--text-muted)' }}>
                                Target Repository:
                            </span>
                            <select
                                className="filter-select"
                                value={selectedRepoId}
                                onChange={(e) => setSelectedRepoId(e.target.value)}
                                disabled={loadingRepos || repositories.length === 0}
                                style={{ minWidth: '240px' }}
                            >
                                {repositories.length === 0 && (
                                    <option value="">{loadingRepos ? 'Loading...' : 'No repositories found'}</option>
                                )}
                                {repositories.map((r) => (
                                    <option key={r.id} value={r.id}>
                                        {r.key || r.id} ({r.mode?.toUpperCase()})
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>

                    {selectedRepo && (
                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                            <span className="badge blue">ID: {selectedRepo.id.slice(0, 8)}...</span>
                            <span className={`badge ${selectedRepo.mode === 'sandbox' ? 'warning' : 'success'}`}>
                                {selectedRepo.mode?.toUpperCase()}
                            </span>
                        </div>
                    )}
                </div>
            </div>

            {/* Test Runners: Two-Column Control Panel */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(420px, 1fr))', gap: '20px' }}>
                {/* Step 1: Generate QR */}
                <div className="panel" style={{ display: 'flex', flexDirection: 'column' }}>
                    <div className="panel-header">
                        <div>
                            <h3 className="panel-title">1. Generate QR Code</h3>
                            <div className="panel-kicker">Calls Bank AGI sandbox <code>/snap/api/v1.0/qr/qr-mpm-generate</code></div>
                        </div>
                        <span className="badge blue">Step 1</span>
                    </div>

                    <div style={{ padding: '20px', display: 'flex', flexDirection: 'column', gap: '16px', flex: 1 }}>
                        <div style={{ display: 'grid', gridTemplateColumns: '1.4fr 1fr', gap: '14px' }}>
                            <div className="field">
                                <label className="label">Amount (IDR) *</label>
                                <input
                                    className="input mono"
                                    type="number"
                                    min={1}
                                    value={amount}
                                    onChange={(e) => setAmount(Number(e.target.value))}
                                />
                            </div>

                            <div className="field">
                                <label className="label">Expiry (Minutes)</label>
                                <input
                                    className="input mono"
                                    type="number"
                                    min={1}
                                    max={1440}
                                    value={expiryPeriod}
                                    onChange={(e) => setExpiryPeriod(Number(e.target.value))}
                                />
                            </div>
                        </div>

                        {/* Amount Presets */}
                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px', flexWrap: 'wrap' }}>
                            <span style={{ fontSize: '11px', color: 'var(--text-muted)', fontWeight: 600 }}>Quick Presets:</span>
                            <button type="button" className="uat-preset-btn" onClick={() => setAmount(10000)}>
                                Rp 10.000 (Standard)
                            </button>
                            <button type="button" className="uat-preset-btn" onClick={() => setAmount(50000)}>
                                Rp 50.000
                            </button>
                            <button type="button" className="uat-preset-btn" onClick={() => { setAmount(10000); setExpiryPeriod(1); }}>
                                1 Min Expiry (#8)
                            </button>
                            <button type="button" className="uat-preset-btn" onClick={() => setAmount(10000001)}>
                                &gt; 10M Limit (#14)
                            </button>
                        </div>

                        <div style={{ marginTop: 'auto', paddingTop: '10px' }}>
                            <button
                                type="button"
                                className="button primary"
                                onClick={() => handleGenerateQr()}
                                disabled={generating || !selectedRepoId}
                                style={{ width: '100%' }}
                            >
                                <IconBeaker /> {generating ? 'Generating QR Code...' : 'Generate Live QR Code'}
                            </button>
                        </div>

                        {qrResult && (
                            <div style={{ padding: '14px', borderRadius: 'var(--radius-md)', background: 'var(--surface-subtle)', border: '1px solid var(--border)', display: 'flex', flexDirection: 'column', gap: '10px' }}>
                                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '8px' }}>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                                        <span className="badge success">QR Active</span>
                                        <code style={{ fontSize: '12px', fontWeight: 700 }}>{qrResult.reference}</code>
                                    </div>
                                    <CopyButton text={qrResult.reference} />
                                </div>

                                {qrResult.qr_string && (
                                    <div style={{ display: 'flex', flexDirection: 'column', gap: '4px' }}>
                                        <span style={{ fontSize: '11px', color: 'var(--text-muted)', fontWeight: 600 }}>QR String Content:</span>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                            <input
                                                className="input mono"
                                                readOnly
                                                value={qrResult.qr_string}
                                                style={{ fontSize: '11.5px', height: '34px' }}
                                            />
                                            <CopyButton text={qrResult.qr_string} />
                                        </div>
                                    </div>
                                )}

                                <div style={{ display: 'flex', gap: '8px', marginTop: '4px' }}>
                                    <button
                                        type="button"
                                        className="button"
                                        onClick={() => handleQueryStatus()}
                                        disabled={querying}
                                        style={{ flex: 1, fontSize: '12px', minHeight: '32px' }}
                                    >
                                        <IconRefresh /> {querying ? 'Querying...' : 'Query Status'}
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>
                </div>

                {/* Step 2: Simulate Bank Notify */}
                <div className="panel" style={{ display: 'flex', flexDirection: 'column' }}>
                    <div className="panel-header">
                        <div>
                            <h3 className="panel-title">2. Simulate Bank Notification</h3>
                            <div className="panel-kicker">Dispatches authenticated callback to <code>/api/bank/agi/notify-qris</code></div>
                        </div>
                        <span className="badge">Step 2</span>
                    </div>

                    <div style={{ padding: '20px', display: 'flex', flexDirection: 'column', gap: '16px', flex: 1 }}>
                        <p style={{ fontSize: '12.5px', color: 'var(--text-muted)', margin: 0, lineHeight: 1.5 }}>
                            Simulates the inbound webhook Bank AGI sends when a customer successfully pays the QRIS code.
                            Updates the local order status and triggers registered merchant callbacks.
                        </p>

                        <div style={{ display: 'grid', gridTemplateColumns: '1.2fr 1fr', gap: '14px' }}>
                            <div className="field">
                                <label className="label">Transaction Status *</label>
                                <select
                                    className="input"
                                    value={notifyStatus}
                                    onChange={(e) => setNotifyStatus(e.target.value)}
                                >
                                    <option value="00">00 — Success (SUCCEED)</option>
                                    <option value="05">05 — Failed (FAILED)</option>
                                    <option value="01">01 — Pending (PENDING)</option>
                                </select>
                            </div>

                            <div className="field">
                                <label className="label">Override Amount</label>
                                <input
                                    className="input mono"
                                    type="number"
                                    placeholder="Leave empty (order amount)"
                                    value={notifyAmount}
                                    onChange={(e) => setNotifyAmount(e.target.value)}
                                />
                            </div>
                        </div>

                        <div style={{ marginTop: 'auto', paddingTop: '10px' }}>
                            <button
                                type="button"
                                className="button primary"
                                onClick={() => handleNotify()}
                                disabled={notifying || !qrResult}
                                style={{ width: '100%' }}
                            >
                                <IconCheck /> {notifying ? 'Sending Callback...' : 'Dispatch Verified Notification'}
                            </button>
                        </div>

                        {notifyResult && (
                            <div style={{ padding: '14px', borderRadius: 'var(--radius-md)', background: 'var(--surface-subtle)', border: '1px solid var(--border)', display: 'flex', flexDirection: 'column', gap: '8px' }}>
                                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                                    <span style={{ fontSize: '12px', fontWeight: 700 }}>Callback Result:</span>
                                    {renderBadge('status', notifyResult.local_status)}
                                </div>
                                <pre className="uat-json-viewer" style={{ maxHeight: '160px' }}>
                                    {JSON.stringify(notifyResult.notify_response, null, 2)}
                                </pre>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Live Query Inspector */}
            {queryResult && (
                <div className="panel">
                    <div className="panel-header">
                        <div>
                            <h3 className="panel-title">3. Live Transaction Status Inspector</h3>
                            <div className="panel-kicker">Real-time status returned by Bank AGI gateway inquiry</div>
                        </div>
                        <span className="badge success">Query OK</span>
                    </div>

                    <div style={{ padding: '20px', display: 'flex', flexDirection: 'column', gap: '16px' }}>
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '14px' }}>
                            <div style={{ padding: '12px 14px', borderRadius: 'var(--radius-md)', background: 'var(--surface-subtle)', border: '1px solid var(--border)' }}>
                                <div style={{ fontSize: '11px', fontWeight: 700, color: 'var(--text-muted)', textTransform: 'uppercase' }}>Reference</div>
                                <div style={{ marginTop: '4px', fontFamily: 'var(--font-mono)', fontWeight: 700, fontSize: '13px' }}>{queryResult.reference}</div>
                            </div>

                            <div style={{ padding: '12px 14px', borderRadius: 'var(--radius-md)', background: 'var(--surface-subtle)', border: '1px solid var(--border)' }}>
                                <div style={{ fontSize: '11px', fontWeight: 700, color: 'var(--text-muted)', textTransform: 'uppercase' }}>Gateway Bill #</div>
                                <div style={{ marginTop: '4px', fontFamily: 'var(--font-mono)', fontWeight: 700, fontSize: '13px' }}>{queryResult.bill_number || '-'}</div>
                            </div>

                            <div style={{ padding: '12px 14px', borderRadius: 'var(--radius-md)', background: 'var(--surface-subtle)', border: '1px solid var(--border)' }}>
                                <div style={{ fontSize: '11px', fontWeight: 700, color: 'var(--text-muted)', textTransform: 'uppercase' }}>Amount</div>
                                <div style={{ marginTop: '4px', fontWeight: 800, fontSize: '14px', color: 'var(--text)' }}>{formatCurrency(queryResult.amount)}</div>
                            </div>

                            <div style={{ padding: '12px 14px', borderRadius: 'var(--radius-md)', background: 'var(--surface-subtle)', border: '1px solid var(--border)' }}>
                                <div style={{ fontSize: '11px', fontWeight: 700, color: 'var(--text-muted)', textTransform: 'uppercase' }}>Middleware Status</div>
                                <div style={{ marginTop: '4px' }}>{renderBadge('status', queryResult.local_status)}</div>
                            </div>
                        </div>

                        <div>
                            <div style={{ fontSize: '11px', fontWeight: 700, color: 'var(--text-muted)', textTransform: 'uppercase', marginBottom: '6px' }}>
                                Bank Raw Response Payload:
                            </div>
                            <pre className="uat-json-viewer">
                                {JSON.stringify(queryResult.bank_status, null, 2)}
                            </pre>
                        </div>
                    </div>
                </div>
            )}

            {/* Scenario Checklist — 18 On Us Suite */}
            <div className="panel">
                <div className="panel-header">
                    <div>
                        <h3 className="panel-title">Automated Scenario Matrix (18 Skenario On Us)</h3>
                        <div className="panel-kicker">Mapped 1:1 against Bank Artha Graha official UAT functional test spreadsheet</div>
                    </div>
                    <span className="badge blue">18 Scenarios</span>
                </div>

                <div className="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th style={{ width: '48px', textAlign: 'center' }}>#</th>
                                <th>Scenario Name</th>
                                <th>Category</th>
                                <th>Expected Code</th>
                                <th>Description / Test Method</th>
                                <th style={{ textAlign: 'right' }}>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {SCENARIOS.map((s) => (
                                <tr key={s.no}>
                                    <td style={{ textAlign: 'center', fontFamily: 'var(--font-mono)', fontWeight: 700 }}>
                                        {s.no}
                                    </td>
                                    <td>
                                        <span style={{ fontWeight: 650, color: 'var(--text)' }}>{s.name}</span>
                                    </td>
                                    <td>
                                        <span className={`uat-scenario-badge ${s.category}`}>
                                            {s.categoryLabel}
                                        </span>
                                    </td>
                                    <td>
                                        <code style={{ fontSize: '11.5px', fontWeight: 700 }}>{s.expectedCode}</code>
                                    </td>
                                    <td style={{ fontSize: '12.5px', color: 'var(--text-muted)', maxWidth: '340px', whiteSpace: 'normal' }}>
                                        {s.how}
                                    </td>
                                    <td style={{ textAlign: 'right' }}>
                                        {s.action === 'negative' && (
                                            <button
                                                type="button"
                                                className="button"
                                                disabled={runningNegative !== ''}
                                                onClick={() => handleNegativeTest(s.case!)}
                                                style={{ minHeight: '30px', padding: '4px 12px', fontSize: '12px' }}
                                            >
                                                {runningNegative === s.case ? 'Testing...' : 'Run Test'}
                                            </button>
                                        )}
                                        {s.action === 'generate' && (
                                            <button
                                                type="button"
                                                className="button"
                                                disabled={generating || !selectedRepoId}
                                                onClick={() => {
                                                    setAmount(s.amount ?? 10000);
                                                    setExpiryPeriod(s.expiry ?? 60);
                                                    handleGenerateQr({ amount: s.amount ?? 10000, expiryPeriod: s.expiry ?? 60 });
                                                }}
                                                style={{ minHeight: '30px', padding: '4px 12px', fontSize: '12px' }}
                                            >
                                                Generate
                                            </button>
                                        )}
                                        {s.action === 'query' && (
                                            <button
                                                type="button"
                                                className="button"
                                                disabled={querying || !qrResult}
                                                onClick={() => handleQueryStatus()}
                                                style={{ minHeight: '30px', padding: '4px 12px', fontSize: '12px' }}
                                            >
                                                Query
                                            </button>
                                        )}
                                        {s.action === 'notify' && (
                                            <button
                                                type="button"
                                                className="button"
                                                disabled={notifying || !qrResult}
                                                onClick={() => {
                                                    setNotifyStatus(s.notifyStatus ?? '00');
                                                    handleNotify({ latestTransactionStatus: s.notifyStatus ?? '00' });
                                                }}
                                                style={{ minHeight: '30px', padding: '4px 12px', fontSize: '12px' }}
                                            >
                                                Notify
                                            </button>
                                        )}
                                        {s.action === 'manual' && (
                                            <span className="badge" title="Requires bank side fixture / special account">
                                                Bank Fixture
                                            </span>
                                        )}
                                        {s.action === 'blocked' && (
                                            <span className="badge danger" title="Requires backend deduplication implementation">
                                                Pending Dedup
                                            </span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {negativeResult && (
                    <div style={{ padding: '16px 20px', borderTop: '1px solid var(--border)', background: 'var(--panel-header-bg)' }}>
                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '8px' }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                <span style={{ fontSize: '12px', fontWeight: 700 }}>Negative Test Execution ({negativeCase}):</span>
                                <span className={`badge ${negativeResult.http_status >= 400 ? 'danger' : 'success'}`}>
                                    HTTP {negativeResult.http_status}
                                </span>
                            </div>
                            <span style={{ fontSize: '11px', color: 'var(--text-muted)' }}>Response verified from Bank AGI controller</span>
                        </div>
                        <pre className="uat-json-viewer">
                            {JSON.stringify(negativeResult.body, null, 2)}
                        </pre>
                    </div>
                )}
            </div>

            {/* Recent Orders Table */}
            <div className="panel">
                <div className="panel-header">
                    <div>
                        <h3 className="panel-title">Recent UAT Transactions</h3>
                        <div className="panel-kicker">Live history of test transactions generated through this console</div>
                    </div>
                    <span className="badge blue">{orders.length} Records</span>
                </div>

                <div className="table-wrap">
                    {loadingOrders ? (
                        <div style={{ padding: '30px', textAlign: 'center', color: 'var(--text-muted)' }}>
                            Loading test transactions...
                        </div>
                    ) : orders.length === 0 ? (
                        <div style={{ padding: '40px', textAlign: 'center', color: 'var(--text-muted)' }}>
                            No UAT transactions recorded yet. Click <strong>Generate Live QR Code</strong> above to create one.
                        </div>
                    ) : (
                        <table>
                            <thead>
                                <tr>
                                    <th>Reference</th>
                                    <th>Bill Number</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Mode</th>
                                    <th>Created At</th>
                                    <th style={{ textAlign: 'right' }}>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {orders.map((o) => (
                                    <tr key={o.reference}>
                                        <td>
                                            <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                                                <code style={{ fontSize: '12px', fontWeight: 650 }}>{o.reference}</code>
                                                <CopyButton text={o.reference} />
                                            </div>
                                        </td>
                                        <td>
                                            <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                                                <code style={{ fontSize: '12px' }}>{o.bill_number || '-'}</code>
                                                {o.bill_number && <CopyButton text={o.bill_number} />}
                                            </div>
                                        </td>
                                        <td style={{ fontWeight: 700, fontFamily: 'var(--font-mono)' }}>
                                            {formatCurrency(o.amount)}
                                        </td>
                                        <td>{renderBadge('status', o.status)}</td>
                                        <td>{renderBadge('mode', o.mode)}</td>
                                        <td style={{ fontSize: '12px', color: 'var(--text-muted)' }}>
                                            {formatDate(o.created_at)}
                                        </td>
                                        <td style={{ textAlign: 'right' }}>
                                            <button
                                                type="button"
                                                className="button"
                                                onClick={() => handleQueryStatus(o.reference)}
                                                style={{ minHeight: '28px', padding: '3px 10px', fontSize: '11.5px' }}
                                            >
                                                Inspect
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            </div>
        </div>
    );
}
