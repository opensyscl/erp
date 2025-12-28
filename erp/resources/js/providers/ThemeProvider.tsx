import { createContext, useContext, useEffect, useState, ReactNode } from 'react';

export type Theme = 'default' | 'cozzy' | 'cozy-cream' | 'midnight' | 'nord' | 'sakura' | 'emerald' | 'cyber';

interface ThemeContextType {
    theme: Theme;
    setTheme: (theme: Theme) => void;
}

const ThemeContext = createContext<ThemeContextType | undefined>(undefined);

interface ThemeProviderProps {
    children: ReactNode;
    defaultTheme?: Theme;
    storageKey?: string;
}

export function ThemeProvider({
    children,
    defaultTheme = 'default',
    storageKey = 'app-theme',
}: ThemeProviderProps) {
    const [theme, setThemeState] = useState<Theme>(() => {
        if (typeof window !== 'undefined') {
            const stored = localStorage.getItem(storageKey) as Theme;
            if (stored && themes.some(t => t.value === stored)) {
                return stored;
            }
        }
        return defaultTheme;
    });

    useEffect(() => {
        const root = document.documentElement;
        root.removeAttribute('data-theme');
        root.setAttribute('data-theme', theme);
        localStorage.setItem(storageKey, theme);
    }, [theme, storageKey]);

    const setTheme = (newTheme: Theme) => {
        setThemeState(newTheme);
    };

    return (
        <ThemeContext.Provider value={{ theme, setTheme }}>
            {children}
        </ThemeContext.Provider>
    );
}

export function useTheme() {
    const context = useContext(ThemeContext);
    if (context === undefined) {
        throw new Error('useTheme must be used within a ThemeProvider');
    }
    return context;
}

// All available themes
export const themes: { value: Theme; label: string; icon: string; description: string }[] = [
    { value: 'default', label: 'Default', icon: '🎨', description: 'Tema clásico azul' },
    { value: 'cozzy', label: 'Cozzy', icon: '☕', description: 'Suave y cálido' },
    { value: 'cozy-cream', label: 'Cozy Cream', icon: '🍦', description: 'Crema pastel' },
    { value: 'midnight', label: 'Midnight', icon: '🌙', description: 'Oscuro elegante' },
    { value: 'nord', label: 'Nord', icon: '❄️', description: 'Frío y minimal' },
    { value: 'sakura', label: 'Sakura', icon: '🌸', description: 'Rosa pastel' },
    { value: 'emerald', label: 'Emerald', icon: '💎', description: 'Verde fintech' },
    { value: 'cyber', label: 'Cyber', icon: '⚡', description: 'Neon oscuro' },
];
