import { useEffect, useState } from 'react';
import { Theme } from '../../model/resource_model';

export function useTheme() {
    const [theme, setTheme] = useState<Theme>(() => {
        const saved = window.localStorage.getItem('backoffice_theme') as Theme;
        if (saved === 'dark' || saved === 'light') return saved;
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    });

    useEffect(() => {
        document.documentElement.setAttribute('data-theme', theme);
        window.localStorage.setItem('backoffice_theme', theme);
    }, [theme]);

    const toggleTheme = () => {
        setTheme((prev: Theme) => (prev === 'dark' ? 'light' : 'dark'));
    };

    return { theme, toggleTheme };
}
