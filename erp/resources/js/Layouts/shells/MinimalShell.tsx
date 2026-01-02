import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';
import { Toaster } from '@/components/ui/sonner';
import { ShellProps } from './ModernShell';

export default function MinimalShell({
    children,
    user,
    settings,
    className = 'min-h-screen',
    isFullMain = false,
    showingNavigationDropdown,
    setShowingNavigationDropdown,
}: PropsWithChildren<ShellProps>) {
    return (
        <div className={`bg-white ${className}`}>
            <div className="h-full">
                {/* Minimal navbar */}
                <nav className="border-b border-gray-200">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6">
                        <div className="flex h-14 justify-between items-center">
                            <Link href="/" className="flex items-center">
                                {settings?.logo ? (
                                    <img src={settings.logo} alt={settings.name} className="h-7 object-contain" />
                                ) : (
                                    <ApplicationLogo className="h-7 w-auto fill-current text-gray-800" />
                                )}
                            </Link>

                            <div className="hidden sm:flex items-center">
                                <Dropdown>
                                    <Dropdown.Trigger>
                                        <button className="flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900">
                                            <div className="w-7 h-7 rounded-full bg-gray-100 flex items-center justify-center text-gray-700 font-medium text-xs">
                                                {user.name.charAt(0)}
                                            </div>
                                            <span className="hidden md:inline">{user.name}</span>
                                        </button>
                                    </Dropdown.Trigger>
                                    <Dropdown.Content>
                                        <Dropdown.Link href={route('profile.edit')}>Profile</Dropdown.Link>
                                        <Dropdown.Link href={route('logout')} method="post" as="button">Log Out</Dropdown.Link>
                                    </Dropdown.Content>
                                </Dropdown>
                            </div>

                            <div className="-me-2 flex items-center sm:hidden">
                                <button
                                    onClick={() => setShowingNavigationDropdown(!showingNavigationDropdown)}
                                    className="inline-flex items-center justify-center rounded-md p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-500"
                                >
                                    <svg className="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                        <path className={!showingNavigationDropdown ? 'inline-flex' : 'hidden'} strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16" />
                                        <path className={showingNavigationDropdown ? 'inline-flex' : 'hidden'} strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    {/* Mobile menu */}
                    <div className={(showingNavigationDropdown ? 'block' : 'hidden') + ' sm:hidden border-t'}>
                        <div className="space-y-1 py-3">
                            <ResponsiveNavLink href={route('dashboard')} active={route().current('dashboard')}>
                                Dashboard
                            </ResponsiveNavLink>
                        </div>
                        <div className="border-t py-3 px-4">
                            <div className="text-sm font-medium text-gray-900">{user.name}</div>
                            <div className="text-xs text-gray-500">{user.email}</div>
                            <div className="mt-2 space-y-1">
                                <ResponsiveNavLink href={route('profile.edit')}>Profile</ResponsiveNavLink>
                                <ResponsiveNavLink method="post" href={route('logout')} as="button">Log Out</ResponsiveNavLink>
                            </div>
                        </div>
                    </div>
                </nav>

                <main className={isFullMain ? 'h-[calc(100vh-3.5rem)]' : ''}>{children}</main>
                <Toaster />
            </div>
        </div>
    );
}
