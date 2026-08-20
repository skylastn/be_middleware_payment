import './bootstrap';
import React, { useEffect, useMemo, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { ResourceKey, RouteInfo } from './src/model/resource_model';
import { User } from './src/model/response_model';
import { resourceDefinitions } from './src/shared/constant/resource_definitions';
import { getStoredToken, removeStoredToken } from './src/shared/utils/auth_utils';
import { useTheme } from './src/shared/hooks/use_theme';
import { AuthService } from './src/services/auth_service';
import { LoginPage } from './src/presentation/auth/login_ui';
import { Shell } from './src/presentation/shell/shell_ui';

function parseRoute(pathname: string): RouteInfo {
    if (pathname === '/' || pathname === '/dashboard') {
        return { page: 'dashboard' };
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

        AuthService.getMe()
            .then(setAuthUser)
            .catch(() => {
                removeStoredToken();
                setAuthUser(null);
            })
            .finally(() => setCheckingAuth(false));
    }, []);

    if (checkingAuth) {
        return <div className="panel empty">Loading backoffice console...</div>;
    }

    if (!authUser) {
        return <LoginPage onLogin={setAuthUser} theme={theme} onToggleTheme={toggleTheme} />;
    }

    return (
        <Shell
            route={route}
            user={authUser}
            theme={theme}
            onToggleTheme={toggleTheme}
            onLogout={() => setAuthUser(null)}
        />
    );
}

const root = document.getElementById('backoffice-root');
if (root) {
    createRoot(root).render(<App />);
}

export default App;
