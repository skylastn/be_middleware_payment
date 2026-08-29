import React from 'react';
import { User } from '@/features/auth/domain/model/response/user_response';
import { Theme } from '@/features/resource/domain/model/resource_model';
import { PanelHeader } from '@/shared/component/ui/panel_header';
import { IconMoon, IconSun } from '@/shared/component/ui/icons';
import { useLoginLogic } from './login_logic';

export interface LoginPageProps {
    onLogin: (user: User) => void;
    theme: Theme;
    onToggleTheme: () => void;
}

export function LoginPage({ onLogin, theme, onToggleTheme }: LoginPageProps): React.JSX.Element {
    const { email, setEmail, password, setPassword, error, loading, handleLogin } = useLoginLogic({ onLogin });

    return (
        <div className="login-page">
            <div className="login-wrap">
                <div className="panel">
                    <PanelHeader
                        title="Backoffice Authentication"
                        kicker="Sign in with your administrator account"
                        aside={
                            <button
                                type="button"
                                className="button"
                                style={{ minHeight: '30px', padding: '4px 10px', fontSize: '12px' }}
                                onClick={onToggleTheme}
                                title={`Switch to ${theme === 'dark' ? 'Light' : 'Dark'} mode`}
                            >
                                {theme === 'dark' ? <IconSun /> : <IconMoon />}
                            </button>
                        }
                    />
                    <form className="form-body" onSubmit={handleLogin}>
                        <label className="field">
                            <span className="label">Email Address</span>
                            <input
                                className="input"
                                type="email"
                                value={email}
                                autoFocus
                                required
                                placeholder="admin@example.com"
                                onChange={(event) => setEmail(event.target.value)}
                            />
                        </label>
                        <label className="field">
                            <span className="label">Password</span>
                            <input
                                className="input"
                                type="password"
                                value={password}
                                required
                                placeholder="••••••••"
                                onChange={(event) => setPassword(event.target.value)}
                            />
                        </label>
                        {error && <div className="alert">{error}</div>}
                        <button className="button primary" type="submit" disabled={loading}>
                            {loading ? 'Authenticating...' : 'Sign In'}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    );
}
