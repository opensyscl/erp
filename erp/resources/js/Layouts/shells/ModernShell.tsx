import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import { Link } from '@inertiajs/react';
import { PropsWithChildren, ReactNode } from 'react';
import { Toaster } from '@/components/ui/sonner';
import { ThemeSwitcherCompact } from '@/components/ThemeSwitcher';
import Breadcrumb from '@/components/Breadcrumb';
import SectionNav, { NavTab } from '@/components/SectionNav';

export interface ShellProps {
    user: { name: string; email: string };
    settings?: { logo?: string; name?: string };
    sectionTabs?: NavTab[];
    className?: string;
    isFullMain?: boolean;
    absoluteNav?: boolean;
    showingNavigationDropdown: boolean;
    setShowingNavigationDropdown: (show: boolean) => void;
}

export default function ModernShell({
    children,
    user,
    settings,
    sectionTabs,
    className = 'min-h-screen',
    isFullMain = false,
    absoluteNav = false,
    showingNavigationDropdown,
    setShowingNavigationDropdown,
}: PropsWithChildren<ShellProps>) {
    return (
        <div className={`bg-gray-100 ${className}`}>
            <div className="h-full">
                <nav className={`border-b border-gray-100 ${absoluteNav ? 'absolute top-0 left-0 right-0 w-full' : ''} bg-white`}>
                    <div className="mx-auto max-w-11xl px-4 sm:px-6 lg:px-8">
                        <div className="flex h-16 justify-between">
                            <div className="flex">
                                <div className="flex shrink-0 items-center">
                                    <Link href="/">
                                        {settings?.logo ? (
                                            <img src={settings.logo} alt={settings.name} className="block h-9 w-auto object-contain" />
                                        ) : (
                                            <ApplicationLogo className="block h-9 w-auto fill-current text-gray-800" />
                                        )}
                                    </Link>
                                </div>

                                <div className="hidden sm:-my-px sm:ms-8 sm:flex sm:items-center">
                                    <Breadcrumb />
                                </div>
                            </div>

                            {/* Center Navigation */}
                            {sectionTabs && sectionTabs.length > 0 && (
                                <div className="hidden sm:flex sm:items-center">
                                    <SectionNav tabs={sectionTabs} />
                                </div>
                            )}

                            <div className="hidden sm:ms-6 sm:flex sm:items-center gap-2">
                                <ThemeSwitcherCompact />
                                <div className="relative">
                                    <Dropdown>
                                        <Dropdown.Trigger>
                                            <span className="inline-flex rounded-md">
                                                <button
                                                    type="button"
                                                    className="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none"
                                                >
                                                    {user.name}
                                                    <svg className="-me-0.5 ms-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fillRule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clipRule="evenodd" />
                                                    </svg>
                                                </button>
                                            </span>
                                        </Dropdown.Trigger>
                                        <Dropdown.Content>
                                            <Dropdown.Link href={route('profile.edit')}>Profile</Dropdown.Link>
                                            <Dropdown.Link href={route('logout')} method="post" as="button">Log Out</Dropdown.Link>
                                        </Dropdown.Content>
                                    </Dropdown>
                                </div>
                            </div>

                            <div className="-me-2 flex items-center sm:hidden">
                                <button
                                    onClick={() => setShowingNavigationDropdown(!showingNavigationDropdown)}
                                    className="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none"
                                >
                                    <svg className="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                        <path className={!showingNavigationDropdown ? 'inline-flex' : 'hidden'} strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16" />
                                        <path className={showingNavigationDropdown ? 'inline-flex' : 'hidden'} strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div className={(showingNavigationDropdown ? 'block' : 'hidden') + ' sm:hidden'}>
                        <div className="space-y-1 pb-3 pt-2">
                            <ResponsiveNavLink href={route('dashboard')} active={route().current('dashboard')}>
                                Dashboard
                            </ResponsiveNavLink>
                        </div>
                        <div className="border-t border-gray-200 pb-1 pt-4">
                            <div className="px-4">
                                <div className="text-base font-medium text-gray-800">{user.name}</div>
                                <div className="text-sm font-medium text-gray-500">{user.email}</div>
                            </div>
                            <div className="mt-3 space-y-1">
                                <ResponsiveNavLink href={route('profile.edit')}>Profile</ResponsiveNavLink>
                                <ResponsiveNavLink method="post" href={route('logout')} as="button">Log Out</ResponsiveNavLink>
                            </div>
                        </div>
                    </div>
                </nav>

                <main className={isFullMain ? 'h-full' : ''}>{children}</main>
                <Toaster />
            </div>
        </div>
    );
}
