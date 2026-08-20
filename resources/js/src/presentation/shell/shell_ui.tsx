import React from 'react';
import { RouteInfo, Theme } from '../../model/resource_model';
import { User } from '../../model/response_model';
import { resourceList } from '../../shared/constant/resource_definitions';
import { AuthService } from '../../services/auth_service';
import { navigate } from '../../shared/utils/format_utils';
import { getResourceIcon, IconDashboard, IconLogs, IconMoon, IconSun } from '../../shared/widget/icons';
import { DashboardPage } from '../dashboard/dashboard_ui';
import { LogsPage } from '../logs/logs_ui';
import { ResourceIndex } from '../resource/resource_index_ui';
import { ResourceForm } from '../resource/resource_form_ui';
import { ResourceShow } from '../resource/resource_show_ui';

export interface ShellProps {
    route: RouteInfo;
    user: User;
    theme: Theme;
    onToggleTheme: () => void;
    onLogout: () => void;
}

export function Shell({ route, user, theme, onToggleTheme, onLogout }: ShellProps): React.JSX.Element {
    const activeResource = route.resource;

    async function logout() {
        await AuthService.logout();
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
                            <span className="brand-subtitle">Unified Backoffice Console</span>
                        </span>
                    </button>
                    <div className="userbar">
                        {/* Theme Toggle Button */}
                        <button
                            type="button"
                            className="button"
                            onClick={onToggleTheme}
                            title={`Switch to ${theme === 'dark' ? 'Light' : 'Dark'} Mode`}
                        >
                            {theme === 'dark' ? <IconSun /> : <IconMoon />}
                            <span>{theme === 'dark' ? 'Light' : 'Dark'}</span>
                        </button>

                        <span className="user-chip">
                            <span className="avatar">{String(user?.name || 'A').slice(0, 1).toUpperCase()}</span>
                            <span>{user.name || 'Administrator'}</span>
                        </span>
                        <button className="button" onClick={logout}>
                            Logout
                        </button>
                    </div>
                </div>

                {/* Modern Navigation Tabs */}
                <nav className="tabs-nav" aria-label="Backoffice navigation">
                    <div className="tabs-container">
                        <button
                            className={`tab ${route.page === 'dashboard' ? 'active' : ''}`}
                            onClick={() => navigate('/dashboard')}
                        >
                            <IconDashboard />
                            <span>Dashboard</span>
                        </button>
                        <button
                            className={`tab ${route.page === 'logs' ? 'active' : ''}`}
                            onClick={() => navigate('/admin/logs')}
                        >
                            <IconLogs />
                            <span>Logs</span>
                        </button>
                        {resourceList.map((item) => (
                            <button
                                className={`tab ${activeResource === item.key ? 'active' : ''}`}
                                key={item.key}
                                onClick={() => navigate(`/admin/${item.key}`)}
                            >
                                {getResourceIcon(item.key)}
                                <span>{item.label}</span>
                            </button>
                        ))}
                    </div>
                </nav>
            </header>

            <main className="content">
                {route.page === 'dashboard' && <DashboardPage />}
                {route.page === 'logs' && <LogsPage />}
                {route.page === 'resource-index' && <ResourceIndex resource={route.resource} />}
                {route.page === 'resource-create' && <ResourceForm resource={route.resource} />}
                {route.page === 'resource-edit' && <ResourceForm resource={route.resource} id={route.id} />}
                {route.page === 'resource-show' && <ResourceShow resource={route.resource} id={route.id} />}
            </main>
        </div>
    );
}
