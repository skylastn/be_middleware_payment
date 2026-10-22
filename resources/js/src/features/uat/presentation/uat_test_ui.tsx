import React from 'react';
import { PageTitle } from '@/shared/component/ui/page_title';
import { IconChevronRight, IconBeaker } from '@/shared/component/ui/icons';
import { navigate } from '@/shared/utils/format_utils';

interface GatewayUatItem {
    key: string;
    name: string;
    code: string;
    type: string;
    description: string;
    available: boolean;
    tags: string[];
    scenariosCount?: number;
}

const UAT_GATEWAYS: GatewayUatItem[] = [
    {
        key: 'bank-agi',
        name: 'Bank Artha Graha',
        code: 'bank_agi',
        type: 'SNAP BI QRIS',
        description: 'Complete 18-scenario test suite for QRIS MPM On Us & Off Us. Includes QR generation, signed notification simulation, and status inquiry.',
        available: true,
        tags: ['QRIS MPM', 'SNAP v1.0', '18 Scenarios', 'Sandbox Ready'],
        scenariosCount: 18,
    },
    {
        key: 'duitku',
        name: 'Duitku Payment Gateway',
        code: 'duitku',
        type: 'Aggregator',
        description: 'Virtual Account, QRIS, and E-Wallet flow simulation for Duitku sandbox integration.',
        available: false,
        tags: ['Virtual Account', 'QRIS', 'Inquiry Callback'],
    },
    {
        key: 'midtrans',
        name: 'Midtrans SNAP',
        code: 'midtrans',
        type: 'Gateway',
        description: 'Payment redirect, Core API, and notification webhook validation suite.',
        available: false,
        tags: ['SNAP Popup', 'Core API', 'Webhooks'],
    },
    {
        key: 'xendit',
        name: 'Xendit',
        code: 'xendit',
        type: 'Aggregator',
        description: 'Invoice checkout, recurring tokenization, and e-wallet verification sandbox.',
        available: false,
        tags: ['Invoice API', 'Virtual Account', 'Cards'],
    },
    {
        key: 'stripe',
        name: 'Stripe Global',
        code: 'stripe',
        type: 'Cards & Wallets',
        description: 'PaymentIntent, 3D Secure verification, and multi-currency transaction testing.',
        available: false,
        tags: ['PaymentIntent', '3D Secure', 'Webhooks'],
    },
    {
        key: 'paprika',
        name: 'Paprika Gaming',
        code: 'paprika',
        type: 'Direct API',
        description: 'Direct QRIS generation and custom settlement callback verification.',
        available: false,
        tags: ['QRIS Direct', 'Fast Settlement'],
    },
];

export function UatTestPage(): React.JSX.Element {
    return (
        <div className="page-container" style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
            <PageTitle
                eyebrow="Integration Lab"
                title="Payment Gateway Testing Hub"
                subtitle="Dedicated test consoles per payment gateway. Switch between Sandbox and Production repositories, run live automated scenario suites, and verify bank responses."
            >
                <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                    <span className="badge blue" style={{ padding: '6px 12px', fontSize: '12px' }}>
                        <IconBeaker /> 1 Active Suite Ready
                    </span>
                </div>
            </PageTitle>

            {/* Quick Metrics Strip */}
            <div className="order-summary-strip">
                <div className="summary-tile blue">
                    <div className="summary-tile-icon">
                        <IconBeaker />
                    </div>
                    <div className="summary-tile-body">
                        <div className="summary-tile-label">Active Test Suite</div>
                        <div className="summary-tile-value">Bank AGI</div>
                    </div>
                </div>

                <div className="summary-tile success">
                    <div className="summary-tile-icon">✓</div>
                    <div className="summary-tile-body">
                        <div className="summary-tile-label">On Us Coverage</div>
                        <div className="summary-tile-value">18 / 18</div>
                    </div>
                </div>

                <div className="summary-tile warning">
                    <div className="summary-tile-icon">⚡</div>
                    <div className="summary-tile-body">
                        <div className="summary-tile-label">Default Environment</div>
                        <div className="summary-tile-value">Sandbox</div>
                    </div>
                </div>

                <div className="summary-tile">
                    <div className="summary-tile-icon">🛡️</div>
                    <div className="summary-tile-body">
                        <div className="summary-tile-label">Standard</div>
                        <div className="summary-tile-value">SNAP BI 1.0</div>
                    </div>
                </div>
            </div>

            {/* Gateway Cards Grid */}
            <div className="uat-gateway-grid">
                {UAT_GATEWAYS.map((gw) => (
                    <div
                        key={gw.key}
                        className={`uat-gateway-card ${gw.available ? 'available' : 'disabled'}`}
                        onClick={() => gw.available && navigate(`/admin/uat-testing/${gw.key}`)}
                        role={gw.available ? 'button' : undefined}
                        tabIndex={gw.available ? 0 : undefined}
                    >
                        <div className="uat-gateway-header">
                            <div className="uat-gateway-brand">
                                <div className={`uat-gateway-icon-box ${gw.available ? '' : 'disabled'}`}>
                                    {gw.name.slice(0, 2).toUpperCase()}
                                </div>
                                <div>
                                    <h3 className="uat-gateway-name">{gw.name}</h3>
                                    <span className="uat-gateway-key">{gw.code}</span>
                                </div>
                            </div>
                            {gw.available ? (
                                <span className="badge success">Live Sandbox</span>
                            ) : (
                                <span className="badge">Coming Soon</span>
                            )}
                        </div>

                        <p className="uat-gateway-desc">{gw.description}</p>

                        <div className="uat-gateway-tags">
                            {gw.tags.map((t) => (
                                <span key={t} className="uat-tag">{t}</span>
                            ))}
                        </div>

                        <div className="uat-gateway-footer">
                            <span style={{ color: 'var(--text-muted)' }}>
                                {gw.available ? `${gw.scenariosCount} test scenarios` : 'In development'}
                            </span>
                            {gw.available ? (
                                <span className="uat-action-link">
                                    Launch Console <IconChevronRight />
                                </span>
                            ) : (
                                <span style={{ color: 'var(--text-subtle)' }}>Unavailable</span>
                            )}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
