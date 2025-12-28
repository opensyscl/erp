import { useTheme, themes, Theme } from '@/providers/ThemeProvider';
import { ChevronDown } from 'lucide-react';
import { useState, useRef, useEffect } from 'react';

export function ThemeSelect() {
    const { theme, setTheme } = useTheme();
    const [open, setOpen] = useState(false);
    const dropdownRef = useRef<HTMLDivElement>(null);

    const currentTheme = themes.find(t => t.value === theme) || themes[0];

    useEffect(() => {
        function handleClickOutside(event: MouseEvent) {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
                setOpen(false);
            }
        }
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    return (
        <div className="relative" ref={dropdownRef}>
            <button
                onClick={() => setOpen(!open)}
                className="flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 transition text-sm"
            >
                <span>{currentTheme.icon}</span>
                <span className="font-medium">{currentTheme.label}</span>
                <ChevronDown className={`w-4 h-4 text-gray-400 transition ${open ? 'rotate-180' : ''}`} />
            </button>

            {open && (
                <div className="absolute right-0 mt-1 w-56 bg-white rounded-xl shadow-lg border border-gray-200 py-1 z-50 animate-in fade-in slide-in-from-top-2 duration-200">
                    {themes.map((t) => (
                        <button
                            key={t.value}
                            onClick={() => {
                                setTheme(t.value);
                                setOpen(false);
                            }}
                            className={`w-full flex items-center gap-3 px-3 py-2.5 text-left hover:bg-gray-50 transition ${
                                theme === t.value ? 'bg-primary/5' : ''
                            }`}
                        >
                            <span className="text-xl">{t.icon}</span>
                            <div className="flex-1">
                                <div className={`font-medium text-sm ${theme === t.value ? 'text-primary' : 'text-gray-900'}`}>
                                    {t.label}
                                </div>
                                <div className="text-xs text-gray-500">{t.description}</div>
                            </div>
                            {theme === t.value && (
                                <svg className="w-4 h-4 text-primary" fill="currentColor" viewBox="0 0 20 20">
                                    <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                                </svg>
                            )}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

// Compact version for navbar (icon only)
export function ThemeSwitcherCompact() {
    const { theme, setTheme } = useTheme();
    const [open, setOpen] = useState(false);
    const dropdownRef = useRef<HTMLDivElement>(null);

    const currentTheme = themes.find(t => t.value === theme) || themes[0];

    useEffect(() => {
        function handleClickOutside(event: MouseEvent) {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
                setOpen(false);
            }
        }
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    return (
        <div className="relative" ref={dropdownRef}>
            <button
                onClick={() => setOpen(!open)}
                className="p-2 rounded-lg hover:bg-gray-100 transition text-xl"
                title="Cambiar tema"
            >
                {currentTheme.icon}
            </button>

            {open && (
                <div className="absolute right-0 mt-1 w-56 bg-white rounded-xl shadow-lg border border-gray-200 py-1 z-50 animate-in fade-in slide-in-from-top-2 duration-200">
                    <div className="px-3 py-2 border-b border-gray-100">
                        <span className="text-xs font-semibold text-gray-500 uppercase tracking-wide">Tema</span>
                    </div>
                    {themes.map((t) => (
                        <button
                            key={t.value}
                            onClick={() => {
                                setTheme(t.value);
                                setOpen(false);
                            }}
                            className={`w-full flex items-center gap-3 px-3 py-2 text-left hover:bg-gray-50 transition ${
                                theme === t.value ? 'bg-primary/5' : ''
                            }`}
                        >
                            <span className="text-lg">{t.icon}</span>
                            <div className="flex-1">
                                <div className={`font-medium text-sm ${theme === t.value ? 'text-primary' : 'text-gray-900'}`}>
                                    {t.label}
                                </div>
                            </div>
                            {theme === t.value && (
                                <svg className="w-4 h-4 text-primary" fill="currentColor" viewBox="0 0 20 20">
                                    <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                                </svg>
                            )}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

// Full theme switcher with buttons
export function ThemeSwitcher() {
    const { theme, setTheme } = useTheme();

    return (
        <div className="flex flex-wrap gap-2">
            {themes.map((t) => (
                <button
                    key={t.value}
                    onClick={() => setTheme(t.value)}
                    className={`flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition ${
                        theme === t.value
                            ? 'bg-primary text-white shadow-md'
                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                    }`}
                    title={t.description}
                >
                    <span>{t.icon}</span>
                    <span>{t.label}</span>
                </button>
            ))}
        </div>
    );
}
