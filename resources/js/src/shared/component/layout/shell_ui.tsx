import React from 'react';
import { User } from '@/features/auth/domain/model/response/user_response';
import { RouteInfo, Theme } from '@/features/resource/domain/model/resource_model';
import { resourceList } from '@/features/resource/domain/constant/resource_definitions';
import { navigate } from '@/shared/utils/format_utils';
import {
    getResourceIcon,
    IconDashboard,
    IconLogs,
    IconMoon,
    IconSun,
} from '@/shared/component/ui/icons';
import { useShellLogic } from './shell_logic';

export interface ShellProps {
    user: User | null;
    route: RouteInfo;
    children: React.ReactNode;
    theme: Theme;
    onToggleTheme: () => void;
    onLogout: () => void;
}

export function Shell({ user, route, children, theme, onToggleTheme, onLogout }: ShellProps): React.JSX.Element {
    const { handleLogout } = useShellLogic({ user, route, theme, onToggleTheme, onLogout });
    const activeResource = route.resource;

    return (
        <div className="shell">
            <header className="topbar">
                <div className="topbar-inner">
                    <button className="brand link-button" onClick={() => navigate('/dashboard')}>
                        <span className="brand-mark">MP</span>
                        <span className="brand-copy">
                            <span className="brand-title">Middleware Payment</span>
                            {/* <span className="brand-subtitle">Unified Backoffice Console</span> */}
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
                            <span>{user?.name || 'Administrator'}</span>
                        </span>
                        <button className="button" onClick={handleLogout}>
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
                {children}
            </main>
        </div>
    );
}
