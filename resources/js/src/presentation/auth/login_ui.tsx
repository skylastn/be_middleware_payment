import React, { useState } from 'react';
import { User } from '../../model/response_model';
import { Theme } from '../../model/resource_model';
import { AuthService } from '../../services/auth_service';
import { navigate } from '../../shared/utils/format_utils';
import { PanelHeader } from '../../shared/widget/panel_header';
import { IconMoon, IconSun } from '../../shared/widget/icons';

export interface LoginPageProps {
    onLogin: (user: User) => void;
    theme: Theme;
    onToggleTheme: () => void;
}

export function LoginPage({ onLogin, theme, onToggleTheme }: LoginPageProps): React.JSX.Element {
    const [email, setEmail] = useState<string>('');
    const [password, setPassword] = useState<string>('');
    const [error, setError] = useState<string>('');
    const [loading, setLoading] = useState<boolean>(false);

    async function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setError('');
        setLoading(true);

        try {
            const user = await AuthService.login(email, password);
            onLogin(user);
            navigate('/dashboard');
        } catch (exception: any) {
            setError(exception.message);
        } finally {
            setLoading(false);
        }
    }

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
                    <form className="form-body" onSubmit={submit}>
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
