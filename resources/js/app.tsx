import './bootstrap';
import React, { useEffect, useMemo, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { ResourceKey, RouteInfo } from '@/features/resource/domain/model/resource_model';
import { resourceDefinitions } from '@/features/resource/domain/constant/resource_definitions';
import { User } from '@/features/auth/domain/model/response/user_response';
import { authService } from '@/features/auth/application/auth_service';
import { LoginPage } from '@/features/auth/presentation/login/login_ui';
import { DashboardPage } from '@/features/dashboard/presentation/dashboard/dashboard_ui';
import { OrderPage } from '@/features/dashboard/presentation/order/order_ui';
import { ProjectPage } from '@/features/dashboard/presentation/project/project_ui';
import { PaymentGatewayPage } from '@/features/dashboard/presentation/payment_gateway/payment_gateway_ui';
import { PaymentRepositoryPage } from '@/features/dashboard/presentation/payment_repository/payment_repository_ui';
import { PaymentMethodPage } from '@/features/dashboard/presentation/payment_method/payment_method_ui';
import { PaymentCategoryPage } from '@/features/dashboard/presentation/payment_category/payment_category_ui';
import { SettingPage } from '@/features/dashboard/presentation/setting/setting_ui';
import { LogsPage } from '@/features/logs/presentation/logs/logs_ui';
import { Shell } from '@/shared/component/layout/shell_ui';
import { useTheme } from '@/shared/hooks/use_theme';
import { getStoredToken, removeStoredToken } from '@/shared/utils/auth_utils';

function parseRoute(pathname: string): RouteInfo {
    if (pathname === '/' || pathname === '/dashboard') {
        return { page: 'dashboard' };
    }

    if (pathname === '/admin/logs' || pathname === '/logs') {
        return { page: 'logs' };
    }

    const match = pathname.match(/^\/admin\/([^/]+)(?:\/([^/]+))?(?:\/(edit))?$/);
    if (!match) {
        return { page: 'dashboard' };
    }

    const [, resourceRaw, id, edit] = match;
    const resource = resourceRaw as ResourceKey;

    if (!resourceDefinitions[resource]) {
        return { page: 'dashboard' };
    }

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

export function App(): React.JSX.Element {
    const [pathname, setPathname] = useState<string>(window.location.pathname);
    const [authUser, setAuthUser] = useState<User | null>(null);
    const [checkingAuth, setCheckingAuth] = useState<boolean>(Boolean(getStoredToken()));
    const { theme, toggleTheme } = useTheme();
    const route = useMemo(() => parseRoute(pathname), [pathname]);

    useEffect(() => {
        const sync = () => setPathname(window.location.pathname);
        window.addEventListener('popstate', sync);

        return () => window.removeEventListener('popstate', sync);
    }, []);

    useEffect(() => {
        if (!getStoredToken()) {
            setCheckingAuth(false);
            return;
        }

        authService
            .getMe()
            .then(setAuthUser)
            .catch(() => {
                removeStoredToken();
                setAuthUser(null);
            })
            .finally(() => setCheckingAuth(false));
    }, []);

    if (checkingAuth) {
        return (
            <div className="app-loading-screen">
                <div className="loading-card">
                    <div className="loading-brand-mark">MP</div>
                    <h2 className="loading-title">Middleware Payment</h2>
                    <div className="loading-spinner" />
                    <p className="loading-subtitle">Initializing console session...</p>
                </div>
            </div>
        );
    }

    if (!authUser) {
        return <LoginPage onLogin={setAuthUser} theme={theme} onToggleTheme={toggleTheme} />;
    }

    const renderContent = () => {
        if (route.page === 'dashboard') {
            return <DashboardPage />;
        }
        if (route.page === 'logs') {
            return <LogsPage />;
        }
        if (
            route.page === 'resource-index' ||
            route.page === 'resource-create' ||
            route.page === 'resource-edit' ||
            route.page === 'resource-show'
        ) {
            switch (route.resource) {
                case 'orders':
                    return <OrderPage mode={route.page} id={route.id} />;
                case 'projects':
                    return <ProjectPage mode={route.page} id={route.id} />;
                case 'payment-gateways':
                    return <PaymentGatewayPage mode={route.page} id={route.id} />;
                case 'payment-repositories':
                    return <PaymentRepositoryPage mode={route.page} id={route.id} />;
                case 'payment-methods':
                    return <PaymentMethodPage mode={route.page} id={route.id} />;
                case 'payment-categories':
                    return <PaymentCategoryPage mode={route.page} id={route.id} />;
                case 'settings':
                    return <SettingPage mode={route.page} id={route.id} />;
                default:
                    return <DashboardPage />;
            }
        }
        return <DashboardPage />;
    };

    return (
        <Shell
            route={route}
            user={authUser}
            theme={theme}
            onToggleTheme={toggleTheme}
            onLogout={() => setAuthUser(null)}
        >
            {renderContent()}
        </Shell>
    );
}

const root = document.getElementById('backoffice-root');
if (root) {
    createRoot(root).render(<App />);
}

export default App;
