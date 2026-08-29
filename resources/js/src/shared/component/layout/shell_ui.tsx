import React, { useState } from 'react';
import { User } from '@/features/auth/domain/model/response/user_response';
import { RouteInfo, Theme } from '@/features/resource/domain/model/resource_model';
import { navigate } from '@/shared/utils/format_utils';
import {
    IconDashboard,
    IconLogs,
    IconLogout,
    IconMenu,
    IconMoon,
    IconOrders,
    IconProjects,
    IconGateways,
    IconRepositories,
    IconMethods,
    IconCategories,
    IconSettings,
    IconSun,
    IconX,
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
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const activeResource = route.resource;

    const navSections = [
        {
            title: 'OVERVIEW',
            items: [
                {
                    key: 'dashboard',
                    label: 'Dashboard',
                    icon: <IconDashboard />,
                    active: route.page === 'dashboard',
                    path: '/dashboard',
                },
                {
                    key: 'orders',
                    label: 'Orders',
                    icon: <IconOrders />,
                    active: activeResource === 'orders',
                    path: '/admin/orders',
                },
                {
                    key: 'projects',
                    label: 'Projects',
                    icon: <IconProjects />,
                    active: activeResource === 'projects',
                    path: '/admin/projects',
                },
            ],
        },
        {
            title: 'GATEWAYS & CONFIG',
            items: [
                {
                    key: 'payment-gateways',
                    label: 'Payment Gateways',
                    icon: <IconGateways />,
                    active: activeResource === 'payment-gateways',
                    path: '/admin/payment-gateways',
                },
                {
                    key: 'payment-repositories',
                    label: 'Payment Repositories',
                    icon: <IconRepositories />,
                    active: activeResource === 'payment-repositories',
                    path: '/admin/payment-repositories',
                },
                {
                    key: 'payment-methods',
                    label: 'Payment Methods',
                    icon: <IconMethods />,
                    active: activeResource === 'payment-methods',
                    path: '/admin/payment-methods',
                },
                {
                    key: 'payment-categories',
                    label: 'Payment Categories',
                    icon: <IconCategories />,
                    active: activeResource === 'payment-categories',
                    path: '/admin/payment-categories',
                },
            ],
        },
        {
            title: 'SYSTEM',
            items: [
                {
                    key: 'settings',
                    label: 'Settings',
                    icon: <IconSettings />,
                    active: activeResource === 'settings',
                    path: '/admin/settings',
                },
                {
                    key: 'logs',
                    label: 'System Logs',
                    icon: <IconLogs />,
                    active: route.page === 'logs',
                    path: '/admin/logs',
                },
            ],
        },
    ];

    const getPageTitle = () => {
        if (route.page === 'dashboard') return 'Dashboard';
        if (route.page === 'logs') return 'System Logs';
        if (activeResource) {
            return activeResource.split('-').map((s) => s.charAt(0).toUpperCase() + s.slice(1)).join(' ');
        }
        return 'Overview';
    };

    return (
        <div className="app-layout">
            {/* Mobile Backdrop */}
            {sidebarOpen && (
                <div className="sidebar-backdrop" onClick={() => setSidebarOpen(false)} />
            )}

            {/* Left Sidebar */}
            <aside className={`app-sidebar ${sidebarOpen ? 'open' : ''}`}>
                <div className="sidebar-header">
                    <button className="brand link-button" onClick={() => navigate('/dashboard')}>
                        <span className="brand-title">Middleware Payment</span>
                    </button>
                    <button
                        type="button"
                        className="mobile-sidebar-close"
                        onClick={() => setSidebarOpen(false)}
                    >
                        <IconX />
                    </button>
                </div>

                <nav className="sidebar-nav">
                    {navSections.map((section) => (
                        <div className="nav-section" key={section.title}>
                            <div className="nav-section-title">{section.title}</div>
                            <div className="nav-section-items">
                                {section.items.map((item) => (
                                    <button
                                        key={item.key}
                                        type="button"
                                        className={`sidebar-nav-item ${item.active ? 'active' : ''}`}
                                        onClick={() => {
                                            navigate(item.path);
                                            setSidebarOpen(false);
                                        }}
                                    >
                                        <span className="nav-icon">{item.icon}</span>
                                        <span className="nav-label">{item.label}</span>
                                    </button>
                                ))}
                            </div>
                        </div>
                    ))}
                </nav>

                <div className="sidebar-footer">
                    <div className="user-profile-row">
                        <span className="avatar">{String(user?.name || 'A').slice(0, 1).toUpperCase()}</span>
                        <div className="user-info">
                            <span className="user-name">{user?.name || 'Administrator'}</span>
                            <span className="user-role">{user?.role || 'Admin'}</span>
                        </div>
                    </div>

                    <div className="sidebar-footer-actions">
                        <button
                            type="button"
                            className="button footer-btn"
                            onClick={onToggleTheme}
                            title={`Switch to ${theme === 'dark' ? 'Light' : 'Dark'} Mode`}
                        >
                            {theme === 'dark' ? <IconSun /> : <IconMoon />}
                            <span>{theme === 'dark' ? 'Light' : 'Dark'}</span>
                        </button>

                        <button
                            type="button"
                            className="button footer-btn danger"
                            onClick={handleLogout}
                            title="Logout"
                        >
                            <IconLogout />
                            <span>Logout</span>
                        </button>
                    </div>
                </div>
            </aside>

            {/* Main Area */}
            <div className="app-main">
                <header className="app-topbar">
                    <div className="topbar-left">
                        <button
                            type="button"
                            className="mobile-sidebar-toggle"
                            onClick={() => setSidebarOpen(!sidebarOpen)}
                            title="Open navigation menu"
                        >
                            <IconMenu />
                        </button>
                        <div className="breadcrumb-nav">
                            <span className="breadcrumb-root">Backoffice</span>
                            <span className="breadcrumb-separator">/</span>
                            <span className="breadcrumb-current">{getPageTitle()}</span>
                        </div>
                    </div>

                    <div className="topbar-right">
                        <div className="user-chip">
                            <span className="avatar">{String(user?.name || 'A').slice(0, 1).toUpperCase()}</span>
                            <span>{user?.name || 'Admin'}</span>
                        </div>
                    </div>
                </header>

                <main className="content">
                    {children}
                </main>
            </div>
        </div>
    );
}
