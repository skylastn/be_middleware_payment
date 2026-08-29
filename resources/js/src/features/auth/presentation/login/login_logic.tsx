import React, { useState } from 'react';
import { User } from '@/features/auth/domain/model/response/user_response';
import { authService } from '@/features/auth/application/auth_service';
import { navigate } from '@/shared/utils/format_utils';

export interface UseLoginLogicProps {
    onLogin: (user: User) => void;
}

export function useLoginLogic({ onLogin }: UseLoginLogicProps) {
    const [email, setEmail] = useState<string>('');
    const [password, setPassword] = useState<string>('');
    const [error, setError] = useState<string>('');
    const [loading, setLoading] = useState<boolean>(false);

    async function handleLogin(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setError('');
        setLoading(true);

        try {
            const data = await authService.login({ email, password });
            onLogin(data.user);
            navigate('/dashboard');
        } catch (exception: any) {
            setError(exception.message || 'Login failed.');
        } finally {
            setLoading(false);
        }
    }

    return {
        email,
        setEmail,
        password,
        setPassword,
        error,
        loading,
        handleLogin,
    };
}
