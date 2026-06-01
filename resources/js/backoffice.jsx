import './bootstrap';
import React, { useEffect, useMemo, useState } from 'react';
import { createRoot } from 'react-dom/client';

const resourceDefinitions = {
    orders: {
        label: 'Orders',
        singular: 'Order',
        readonly: true,
        columns: ['reference', 'type', 'payment_method', 'status', 'mode', 'created_at'],
        fields: {},
        endpoints: {
            list: '/api/order',
            show: (id) => `/api/order/${id}`,
            resend: (id) => `/api/order/${id}/resend-callback`,
        },
    },
    projects: {
        label: 'Projects',
        singular: 'Project',
        columns: ['name', 'type', 'slug', 'callback', 'created_at'],
        fields: {
            name: { type: 'text', required: true },
            type: { type: 'text', required: true },
            slug: { type: 'select', required: true, options: ['midtrans', 'xendit', 'duitku', 'spnpay', 'stripe'] },
            callback: { type: 'textarea', required: true },
        },
        readonlyFields: {
            key: { type: 'text' },
            secure: { type: 'text' },
            value: { type: 'textarea' },
        },
        endpoints: {
            list: '/api/project',
            show: (id) => `/api/project/${id}`,
            create: '/api/project/create',
            update: (id) => `/api/project/${id}`,
            delete: (id) => `/api/project/${id}`,
        },
    },
    'payment-gateways': {
        label: 'Payment Gateways',
        singular: 'Payment Gateway',
        columns: ['key', 'name', 'description', 'created_at'],
        fields: {
            key: { type: 'text', required: true },
            name: { type: 'text', required: true },
            description: { type: 'textarea', required: true },
        },
        endpoints: {
            list: '/api/payment/getPaymentGateway',
            show: (id) => `/api/payment/gateway/${id}`,
            create: '/api/payment/gateway/create',
            update: (id) => `/api/payment/gateway/${id}`,
            delete: (id) => `/api/payment/gateway/${id}`,
        },
    },
    'payment-repositories': {
        label: 'Payment Repositories',
        singular: 'Payment Repository',
        columns: ['key', 'payment_gateway_id', 'mode', 'value', 'created_at'],
        fields: {
            payment_gateway_id: { label: 'Payment Gateway ID', type: 'text', required: true },
            key: { type: 'text' },
            mode: { type: 'select', required: true, options: ['sandbox', 'prod'] },
            value: { type: 'json', required: true },
        },
        endpoints: {
            list: '/api/payment/getPaymentRepository',
            show: (id) => `/api/payment/repository/${id}`,
            create: '/api/payment/repository/create',
            update: (id) => `/api/payment/repository/${id}`,
            delete: (id) => `/api/payment/repository/${id}`,
        },
    },
    'payment-methods': {
        label: 'Payment Methods',
        singular: 'Payment Method',
        columns: ['key', 'name', 'type', 'from', 'bankCode', 'value'],
        fields: {
            key: { type: 'text', required: true },
            name: { type: 'text', required: true },
            type: { type: 'text', required: true },
            from: { type: 'text', required: true },
            bankCode: { label: 'Bank Code', type: 'text' },
            value: { type: 'text' },
        },
        endpoints: {
            list: '/api/payment/getPaymentMethod',
            show: (id) => `/api/payment/method/${id}`,
            create: '/api/payment/method/create',
            update: (id) => `/api/payment/method/${id}`,
            delete: (id) => `/api/payment/method/${id}`,
        },
    },
    'payment-categories': {
        label: 'Payment Categories',
        singular: 'Payment Category',
        columns: ['key', 'title', 'detail', 'created_at'],
        fields: {
            key: { type: 'text', required: true },
            title: { type: 'text', required: true },
            detail: { type: 'textarea', required: true },
        },
        endpoints: {
            list: '/api/payment/getPaymentCategory',
            show: (id) => `/api/payment/category/${id}`,
            create: '/api/payment/category/create',
            update: (id) => `/api/payment/category/${id}`,
            delete: (id) => `/api/payment/category/${id}`,
        },
    },
    settings: {
        label: 'Settings',
        singular: 'Setting',
        columns: ['key', 'value', 'updated_at'],
        fields: {
            key: { type: 'text', required: true },
            value: { type: 'textarea', required: true },
        },
        endpoints: {
            list: '/api/payment/getSetting',
            show: (id) => `/api/payment/setting/${id}`,
            create: '/api/payment/setting/create',
            update: (id) => `/api/payment/setting/${id}`,
            delete: (id) => `/api/payment/setting/${id}`,
        },
    },
};

const resources = Object.entries(resourceDefinitions).map(([key, definition]) => ({
    key,
    label: definition.label,
}));

const tokenKey = 'backoffice_api_token';

function storedToken() {
    return window.localStorage.getItem(tokenKey) || '';
}

async function api(url, options = {}) {
    const token = storedToken();
    const response = await fetch(url, {
        ...options,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
            ...(options.headers || {}),
        },
    });

    if (!response.ok) {
        if (response.status === 401) {
            window.localStorage.removeItem(tokenKey);
        }

        const error = await response.json().catch(() => ({}));
        const message = error.message || Object.values(error.errors || {}).flat()[0] || `Request failed with ${response.status}`;
        throw new Error(message);
    }

    return response.json();
}

async function login(email, password) {
    const data = await api('/api/admin/login', {
        method: 'POST',
        body: JSON.stringify({ email, password }),
    });

    window.localStorage.setItem(tokenKey, data.token);

    return data.user;
}

function navigate(path) {
    window.history.pushState({}, '', path);
    window.dispatchEvent(new PopStateEvent('popstate'));
}

function title(value) {
    return String(value || '').replaceAll('_', ' ');
}

function dataItems(payload) {
    return Array.isArray(payload?.data) ? payload.data : [];
}

function dataRecord(payload) {
    return payload?.data || payload?.record || {};
}

function pagination(payload) {
    const perPage = Number(payload?.perPage || 15);
    const total = Number(payload?.total || dataItems(payload).length);
    const currentPage = Number(payload?.currentPage || 1);

    return {
        currentPage,
        lastPage: Math.max(1, Math.ceil(total / Math.max(perPage, 1))),
        from: total ? ((currentPage - 1) * perPage) + 1 : 0,
        to: Math.min(currentPage * perPage, total),
        total,
        previousPageUrl: currentPage > 1 ? `${window.location.pathname}?page=${currentPage - 1}` : null,
        nextPageUrl: currentPage * perPage < total ? `${window.location.pathname}?page=${currentPage + 1}` : null,
    };
}

function displayValue(value) {
    if (Array.isArray(value) || (value && typeof value === 'object')) {
        return JSON.stringify(value, null, 2);
    }

    return String(value ?? '');
}

function StatCard({ label, value, note, tone = '' }) {
    return (
        <div className={`panel stat ${tone}`}>
            <div className="stat-label">{label}</div>
            <div className="stat-value">{Number(value || 0).toLocaleString()}</div>
            <div className="stat-note">{note}</div>
        </div>
    );
}

function DashboardPage() {
    const [data, setData] = useState(null);
    const [error, setError] = useState('');

    useEffect(() => {
        api('/api/admin/dashboard')
            .then(setData)
            .catch((exception) => setError(exception.message));
    }, []);

    if (error) {
        return <div className="panel empty">{error}</div>;
    }

    if (!data) {
        return <div className="panel empty">Loading dashboard.</div>;
    }

    const maxMode = Math.max(...data.modeCounts.map((mode) => Number(mode.total || 0)), 1);

    return (
        <>
            <PageTitle eyebrow="Live overview" title="Payment Monitoring" subtitle="Operational snapshot for orders, projects, and payment gateway configuration.">
                <span className="timestamp">Updated {data.updatedAt}</span>
            </PageTitle>

            <section className="grid stats">
                <StatCard label="Total Orders" value={data.summary.orders} note="All captured payment orders" tone="blue" />
                <StatCard label="Success" value={data.summary.paidOrders} note="Orders marked as successful" />
                <StatCard label="Pending" value={data.summary.pendingOrders} note="Waiting for callback or payment" tone="warning" />
                <StatCard label="Failed / Expired" value={data.summary.failedOrders} note="Failed and expired payments" tone="danger" />
            </section>

            <section className="grid stats">
                <StatCard label="Projects" value={data.summary.projects} note="Registered clients" />
                <StatCard label="Gateways" value={data.summary.paymentGateways} note="Gateway providers" tone="purple" />
                <StatCard label="Repositories" value={data.summary.paymentRepositories} note="Credential sets" tone="blue" />
                <StatCard label="Methods" value={data.summary.paymentMethods} note="Available methods" />
            </section>

            <section className="chart-grid">
                <div className="panel">
                    <PanelHeader title="Status Mix" kicker="Success, pending, and failed distribution" />
                    <div className="donut" style={{ '--success-deg': `${data.statusMix.successDeg}deg`, '--pending-deg': `${data.statusMix.pendingDeg}deg` }} />
                    <div className="list">
                        <ListRow label="Success" value={data.statusMix.success} />
                        <ListRow label="Pending" value={data.statusMix.pending} />
                        <ListRow label="Failed / Expired" value={data.statusMix.failedExpired} />
                    </div>
                </div>
                <div className="panel">
                    <PanelHeader title="Mode Volume" kicker="Order traffic by environment" />
                    <div className="bar-list">
                        {data.modeCounts.map((mode) => (
                            <div className="bar-row" key={mode.mode}>
                                <span className="list-title">{mode.mode}</span>
                                <span className="bar-track">
                                    <span className={`bar-fill ${mode.mode === 'prod' ? 'blue' : 'warning'}`} style={{ width: `${Math.max(4, Math.round((Number(mode.total || 0) / maxMode) * 100))}%` }} />
                                </span>
                                <strong>{Number(mode.total || 0).toLocaleString()}</strong>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            <section className="grid columns">
                <div className="panel">
                    <PanelHeader title="Recent Orders" kicker="Latest payment activity" aside={`${data.recentOrders.length} latest`} />
                    <DataTable columns={['Reference', 'Project', 'Method', 'Status', 'Mode', 'Created']}>
                        {data.recentOrders.map((order) => (
                            <tr key={order.reference}>
                                <td className="mono">{order.reference}</td>
                                <td>{order.type}</td>
                                <td>{order.paymentMethod}</td>
                                <td><span className={`badge ${order.statusClass}`}>{order.status}</span></td>
                                <td>{order.mode}</td>
                                <td>{order.createdAt || '-'}</td>
                            </tr>
                        ))}
                    </DataTable>
                </div>
                <div className="panel">
                    <PanelHeader title="Payment Repositories" kicker="Gateway credentials by mode" />
                    <div className="list">
                        {data.repositories.map((repository) => (
                            <div className="list-row" key={repository.id}>
                                <div className="list-main">
                                    <span className="list-title">{repository.gateway}</span>
                                    <div className="muted mono">{repository.id}</div>
                                </div>
                                <span className="badge">{repository.mode}</span>
                            </div>
                        ))}
                    </div>
                </div>
            </section>
        </>
    );
}

function ResourceIndex({ resource }) {
    const definition = resourceDefinitions[resource];
    const [payload, setPayload] = useState(null);
    const [error, setError] = useState('');

    const load = (url = definition.endpoints.list) => {
        setError('');
        api(url).then(setPayload).catch((exception) => setError(exception.message));
    };

    useEffect(() => {
        setPayload(null);
        load();
    }, [resource]);

    if (error) {
        return <div className="panel empty">{error}</div>;
    }

    if (!payload) {
        return <div className="panel empty">Loading records.</div>;
    }

    const records = dataItems(payload);
    const page = pagination(payload);

    async function destroy(record) {
        if (!window.confirm('Delete this record?')) {
            return;
        }

        await api(definition.endpoints.delete(record.id), { method: 'DELETE' });
        load();
    }

    async function resend(record) {
        if (!window.confirm('Resend callback for this order?')) {
            return;
        }

        await api(definition.endpoints.resend(record.id), { method: 'POST' });
        load();
    }

    return (
        <>
            <PageTitle
                eyebrow="Backoffice"
                title={definition.label}
                subtitle={definition.readonly ? 'View records and run available operational actions.' : `View, create, update, and delete ${definition.label.toLowerCase()}.`}
            >
                {!definition.readonly && <button className="button primary" onClick={() => navigate(`/admin/${resource}/create`)}>Create {definition.singular}</button>}
            </PageTitle>

            <div className="panel">
                <DataTable columns={[...definition.columns.map(title), '']}>
                    {records.length ? records.map((record) => (
                        <tr key={record.id}>
                            {definition.columns.map((column) => {
                                const fullValue = displayValue(record[column] || '-');
                                const max = resource === 'payment-repositories' && column === 'value' ? 250 : null;
                                const value = max && fullValue.length > max ? `${fullValue.slice(0, max)}...` : fullValue;

                                return <td className={['id', 'value', 'callback', 'description'].includes(column) ? 'mono' : ''} title={fullValue} key={column}>{value}</td>;
                            })}
                            <td>
                                <div className="actions">
                                    {resource === 'orders' && <button className="button" onClick={() => navigate(`/admin/${resource}/${record.id}`)}>View</button>}
                                    {resource === 'orders' && record.status === 'SUCCESS' && <button className="button" onClick={() => resend(record)}>Resend Callback</button>}
                                    {!definition.readonly && <button className="button" onClick={() => navigate(`/admin/${resource}/${record.id}/edit`)}>Edit</button>}
                                    {!definition.readonly && <button className="button danger" onClick={() => destroy(record)}>Delete</button>}
                                </div>
                            </td>
                        </tr>
                    )) : <tr><td className="empty" colSpan={definition.columns.length + 1}>No records found.</td></tr>}
                </DataTable>
            </div>

            <div className="pagination">
                <div>Showing {page.from || 0} to {page.to || 0} of {page.total} results</div>
                <div className="pagination-actions">
                    <button className="button" disabled={page.currentPage <= 1} onClick={() => load(`${definition.endpoints.list}?page=${page.currentPage - 1}`)}>Previous</button>
                    <span className="button">{page.currentPage} / {page.lastPage}</span>
                    <button className="button" disabled={page.currentPage >= page.lastPage} onClick={() => load(`${definition.endpoints.list}?page=${page.currentPage + 1}`)}>Next</button>
                </div>
            </div>
        </>
    );
}

function ResourceForm({ resource, id }) {
    const definition = resourceDefinitions[resource];
    const isEdit = Boolean(id);
    const [values, setValues] = useState({});
    const [readonlyValues, setReadonlyValues] = useState({});
    const [error, setError] = useState('');

    useEffect(() => {
        setError('');
        setValues({});
        setReadonlyValues({});
        if (!isEdit) {
            return;
        }

        api(definition.endpoints.show(id))
            .then((payload) => {
                const record = dataRecord(payload);
                setValues(record || {});
                setReadonlyValues(record || {});
            })
            .catch((exception) => setError(exception.message));
    }, [resource, id, isEdit]);

    if (error) {
        return <div className="panel empty">{error}</div>;
    }

    async function submit(event) {
        event.preventDefault();
        try {
            await api(isEdit ? definition.endpoints.update(id) : definition.endpoints.create, {
                method: isEdit ? 'PUT' : 'POST',
                body: JSON.stringify(values),
            });
            navigate(`/admin/${resource}`);
        } catch (exception) {
            setError(exception.message);
        }
    }

    return (
        <>
            <PageTitle eyebrow="Backoffice" title={`${isEdit ? 'Edit' : 'Create'} ${definition.singular}`} subtitle={`Manage ${definition.singular.toLowerCase()} details.`}>
                <button className="button" onClick={() => navigate(`/admin/${resource}`)}>Back</button>
            </PageTitle>

            <form className="panel form-grid" onSubmit={submit}>
                {Object.entries(definition.fields).map(([name, field]) => (
                    <Field key={name} name={name} field={field} value={values[name] ?? field.default ?? ''} onChange={(value) => setValues((current) => ({ ...current, [name]: value }))} />
                ))}

                {isEdit && Object.entries(definition.readonlyFields || {}).map(([name, field]) => (
                    <Field key={name} name={name} field={field} value={readonlyValues[name] ?? ''} readonly />
                ))}

                <div className="field full">
                    <button className="button primary" type="submit">{isEdit ? 'Save Changes' : 'Create Record'}</button>
                </div>
            </form>
        </>
    );
}

function ResourceShow({ resource, id }) {
    const definition = resourceDefinitions[resource];
    const [payload, setPayload] = useState(null);
    const [error, setError] = useState('');

    useEffect(() => {
        api(definition.endpoints.show(id)).then(setPayload).catch((exception) => setError(exception.message));
    }, [resource, id]);

    if (error) {
        return <div className="panel empty">{error}</div>;
    }

    if (!payload) {
        return <div className="panel empty">Loading record.</div>;
    }

    const record = dataRecord(payload);

    return (
        <>
            <PageTitle eyebrow="Backoffice" title={`View ${definition.singular}`} subtitle="Read-only operational details.">
                <button className="button" onClick={() => navigate(`/admin/${resource}`)}>Back</button>
            </PageTitle>
            <div className="panel detail-list">
                {Object.entries(record).map(([name, value]) => (
                    <div className={`detail-row ${displayValue(value).length > 80 ? 'wide' : ''}`} key={name}>
                        <span className="label">{title(name)}</span>
                        <span className="mono">{displayValue(value) || '-'}</span>
                    </div>
                ))}
            </div>
        </>
    );
}

function Field({ name, field, value, onChange = () => {}, readonly = false }) {
    const type = field.type || 'text';
    const label = field.label || title(name);
    const className = `field ${['textarea', 'json'].includes(type) ? 'full' : ''}`;

    return (
        <label className={className}>
            <span className="label">{label}</span>
            {type === 'select' ? (
                <select className="input" value={value || ''} disabled={readonly} required={field.required} onChange={(event) => onChange(event.target.value)}>
                    {(field.options || []).map((option) => <option value={option} key={option}>{option}</option>)}
                </select>
            ) : ['textarea', 'json'].includes(type) ? (
                <textarea className="input mono" value={value || ''} readOnly={readonly} required={field.required} onChange={(event) => onChange(event.target.value)} />
            ) : (
                <input className={readonly ? 'input mono' : 'input'} value={value || ''} readOnly={readonly} required={field.required} onChange={(event) => onChange(event.target.value)} />
            )}
        </label>
    );
}

function PageTitle({ eyebrow, title, subtitle, children }) {
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

function PanelHeader({ title, kicker, aside }) {
    return (
        <div className="panel-header">
            <div>
                <h2 className="panel-title">{title}</h2>
                <div className="panel-kicker">{kicker}</div>
            </div>
            {aside && <span className="muted">{aside}</span>}
        </div>
    );
}

function ListRow({ label, value }) {
    return (
        <div className="list-row">
            <span className="list-title">{label}</span>
            <strong>{Number(value || 0).toLocaleString()}</strong>
        </div>
    );
}

function DataTable({ columns, children }) {
    return (
        <div className="table-wrap">
            <table>
                <thead>
                    <tr>{columns.map((column) => <th key={column}>{column}</th>)}</tr>
                </thead>
                <tbody>{children}</tbody>
            </table>
        </div>
    );
}

function LoginPage({ onLogin }) {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);

    async function submit(event) {
        event.preventDefault();
        setError('');
        setLoading(true);

        try {
            onLogin(await login(email, password));
            navigate('/dashboard');
        } catch (exception) {
            setError(exception.message);
        } finally {
            setLoading(false);
        }
    }

    return (
        <div className="login-page">
            <div className="login-wrap">
                <div className="panel">
                    <PanelHeader title="Backoffice Login" kicker="Sign in with an admin API account" />
                    <form className="form-body" onSubmit={submit}>
                        <label className="field">
                            <span className="label">Email</span>
                            <input className="input" type="email" value={email} autoFocus required onChange={(event) => setEmail(event.target.value)} />
                        </label>
                        <label className="field">
                            <span className="label">Password</span>
                            <input className="input" type="password" value={password} required onChange={(event) => setPassword(event.target.value)} />
                        </label>
                        {error && <div className="alert">{error}</div>}
                        <button className="button primary" type="submit" disabled={loading}>{loading ? 'Signing in' : 'Login'}</button>
                    </form>
                </div>
            </div>
        </div>
    );
}

function Shell({ route, user, onLogout }) {
    const activeResource = route.resource;

    async function logout() {
        await api('/api/admin/logout', { method: 'POST' }).catch(() => null);
        window.localStorage.removeItem(tokenKey);
        onLogout();
        navigate('/login');
    }

    return (
        <div className="shell">
            <header className="topbar">
                <div className="topbar-inner">
                    <button className="brand link-button" onClick={() => navigate('/dashboard')}>
                        <span className="brand-mark">MP</span>
                        <span className="brand-copy">
                            <span className="brand-title">Middleware Payment</span>
                            <span className="brand-subtitle">Backoffice dashboard</span>
                        </span>
                    </button>
                    <div className="userbar">
                        <span className="user-chip">
                            <span className="avatar">{String(user?.name || 'A').slice(0, 1).toUpperCase()}</span>
                            <span>{user.name || 'Administrator'}</span>
                        </span>
                        <button className="button" onClick={logout}>Logout</button>
                    </div>
                </div>
                <nav className="tabs" aria-label="Backoffice navigation">
                    <button className={`tab link-button ${route.page === 'dashboard' ? 'active' : ''}`} onClick={() => navigate('/dashboard')}>Dashboard</button>
                    {resources.map((item) => (
                        <button className={`tab link-button ${activeResource === item.key ? 'active' : ''}`} key={item.key} onClick={() => navigate(`/admin/${item.key}`)}>{item.label}</button>
                    ))}
                </nav>
            </header>
            <main className="content">
                {route.page === 'dashboard' && <DashboardPage />}
                {route.page === 'resource-index' && <ResourceIndex resource={route.resource} />}
                {route.page === 'resource-create' && <ResourceForm resource={route.resource} />}
                {route.page === 'resource-edit' && <ResourceForm resource={route.resource} id={route.id} />}
                {route.page === 'resource-show' && <ResourceShow resource={route.resource} id={route.id} />}
            </main>
        </div>
    );
}

function parseRoute(pathname) {
    if (pathname === '/' || pathname === '/dashboard') {
        return { page: 'dashboard' };
    }

    const match = pathname.match(/^\/admin\/([^/]+)(?:\/([^/]+))?(?:\/(edit))?$/);
    if (!match) {
        return { page: 'dashboard' };
    }

    const [, resource, id, edit] = match;
    if (id === 'create') {
        return { page: 'resource-create', resource };
    }

    if (id && edit) {
        return { page: 'resource-edit', resource, id };
    }

    if (id) {
        return { page: 'resource-show', resource, id };
    }

    return { page: 'resource-index', resource };
}

function App() {
    const [pathname, setPathname] = useState(window.location.pathname);
    const [authUser, setAuthUser] = useState(null);
    const [checkingAuth, setCheckingAuth] = useState(Boolean(storedToken()));
    const route = useMemo(() => parseRoute(pathname), [pathname]);

    useEffect(() => {
        const sync = () => setPathname(window.location.pathname);
        window.addEventListener('popstate', sync);

        return () => window.removeEventListener('popstate', sync);
    }, []);

    useEffect(() => {
        if (!storedToken()) {
            setCheckingAuth(false);
            return;
        }

        api('/api/admin/me')
            .then((data) => setAuthUser(data.user))
            .catch(() => {
                window.localStorage.removeItem(tokenKey);
                setAuthUser(null);
            })
            .finally(() => setCheckingAuth(false));
    }, []);

    if (checkingAuth) {
        return <div className="panel empty">Loading backoffice.</div>;
    }

    if (!authUser) {
        return <LoginPage onLogin={setAuthUser} />;
    }

    return <Shell route={route} user={authUser} onLogout={() => setAuthUser(null)} />;
}

const root = document.getElementById('backoffice-root');
if (root) {
    createRoot(root).render(<App />);
}
