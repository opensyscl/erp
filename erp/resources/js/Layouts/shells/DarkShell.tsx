import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren } from 'react';
import { Toaster } from '@/components/ui/sonner';
import { ThemeSwitcherCompact } from '@/components/ThemeSwitcher';
import { useTenantRoute } from '@/Hooks/useTenantRoute';
import { ShellProps } from './ModernShell';
import { PageProps } from '@/types';
import {
    Package,
    ShoppingCart,
    BarChart3,
    Calendar,
    Settings,
    Home,
    Menu,
    X,
    LogOut,
    User
} from 'lucide-react';

export default function DarkShell({
    children,
    user,
    settings,
    className = 'min-h-screen',
    isFullMain = false,
    showingNavigationDropdown,
    setShowingNavigationDropdown,
}: PropsWithChildren<ShellProps>) {
    const tRoute = useTenantRoute();
    const { tenant } = usePage<PageProps>().props;

    const logo = settings?.logo || tenant?.logo;
    const storeName = settings?.name || tenant?.name || 'Tienda';

    const navItems = [
        { name: 'Inicio', href: tRoute('dashboard'), icon: Home },
        { name: 'Inventario', href: tRoute('inventory.index'), icon: Package },
        { name: 'Punto de Venta', href: tRoute('pos.index'), icon: ShoppingCart },
        { name: 'Ventas', href: tRoute('sales.index'), icon: BarChart3 },
        { name: 'Horarios', href: tRoute('schedules.index'), icon: Calendar },
        { name: 'Configuración', href: tRoute('settings.index'), icon: Settings },
    ];

    return (
        <div className={`bg-gray-950 ${className}`}>
            {/* Mobile header */}
            <div className="lg:hidden fixed top-0 left-0 right-0 z-40 bg-gray-900 border-b border-gray-800 h-14 flex items-center px-4">
                <button
                    onClick={() => setShowingNavigationDropdown(!showingNavigationDropdown)}
                    className="p-2 rounded-md text-gray-400 hover:bg-gray-800"
                >
                    {showingNavigationDropdown ? <X className="w-6 h-6" /> : <Menu className="w-6 h-6" />}
                </button>
                <div className="ml-3 flex-1">
                    {logo ? (
                        <img src={logo} alt={storeName} className="h-8 object-contain brightness-0 invert" />
                    ) : (
                        <ApplicationLogo className="h-8 w-auto fill-current text-white" />
                    )}
                </div>
            </div>

            {/* Dark Sidebar */}
            <aside className={`fixed inset-y-0 left-0 z-30 w-64 bg-gray-900 border-r border-gray-800 transform transition-transform duration-300 lg:translate-x-0 ${showingNavigationDropdown ? 'translate-x-0' : '-translate-x-full'}`}>
                <div className="h-16 flex items-center px-6 border-b border-gray-800">
                    <Link href="/" className="flex items-center gap-3">
                        {logo ? (
                            <img src={logo} alt={storeName} className="h-8 object-contain brightness-0 invert" />
                        ) : (
                            <ApplicationLogo className="h-8 w-auto fill-current text-white" />
                        )}
                        <span className="text-white font-semibold text-lg truncate">{storeName}</span>
                    </Link>
                </div>

                <nav className="p-4 space-y-1">
                    {navItems.map((item) => {
                        const Icon = item.icon;
                        const isActive = window.location.pathname.includes(item.href.split('?')[0]);
                        return (
                            <Link
                                key={item.name}
                                href={item.href}
                                className={`flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-all duration-200 ${
                                    isActive
                                        ? 'bg-gradient-to-r from-primary to-primary/80 text-white shadow-lg shadow-primary/20'
                                        : 'text-gray-400 hover:text-white hover:bg-gray-800'
                                }`}
                            >
                                <Icon className="w-5 h-5" />
                                {item.name}
                            </Link>
                        );
                    })}
                </nav>

                <div className="absolute bottom-0 left-0 right-0 p-4 border-t border-gray-800">
                    <div className="flex items-center gap-3 px-4 py-3 rounded-lg bg-gray-800/50">
                        <div className="w-10 h-10 rounded-full bg-gradient-to-br from-primary to-purple-600 flex items-center justify-center text-white font-bold text-lg">
                            {user.name.charAt(0)}
                        </div>
                        <div className="flex-1 min-w-0">
                            <div className="font-medium text-white truncate">{user.name}</div>
                            <div className="text-xs text-gray-500 truncate">{user.email}</div>
                        </div>
                    </div>
                    <div className="mt-2 flex gap-2">
                        <Link
                            href={route('profile.edit')}
                            className="flex-1 flex items-center justify-center gap-2 px-3 py-2 text-xs text-gray-400 hover:text-white hover:bg-gray-800 rounded-lg transition-colors"
                        >
                            <User className="w-4 h-4" />
                            Perfil
                        </Link>
                        <Link
                            href={route('logout')}
                            method="post"
                            as="button"
                            className="flex-1 flex items-center justify-center gap-2 px-3 py-2 text-xs text-gray-400 hover:text-red-400 hover:bg-gray-800 rounded-lg transition-colors"
                        >
                            <LogOut className="w-4 h-4" />
                            Salir
                        </Link>
                    </div>
                </div>
            </aside>

            {/* Main content area */}
            <div className="lg:ml-64 pt-14 lg:pt-0">
                {/* Dark top bar */}
                <header className="hidden lg:flex h-16 bg-gray-900 border-b border-gray-800 items-center justify-between px-6">
                    <div className="flex-1" />
                    <div className="flex items-center gap-4">
                        <ThemeSwitcherCompact />
                    </div>
                </header>

                <main className={`${isFullMain ? 'h-[calc(100vh-4rem)]' : ''} bg-gray-950`}>
                    <div className="bg-gray-950 min-h-full">
                        {children}
                    </div>
                </main>
                <Toaster theme="dark" />
            </div>

            {/* Mobile overlay */}
            {showingNavigationDropdown && (
                <div
                    className="fixed inset-0 bg-black/70 z-20 lg:hidden backdrop-blur-sm"
                    onClick={() => setShowingNavigationDropdown(false)}
                />
            )}
        </div>
    );
}
