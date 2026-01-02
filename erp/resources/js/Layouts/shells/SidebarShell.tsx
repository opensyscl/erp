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
    DollarSign,
    Wallet,
    ListTodo,
    PackageMinus,
    AlertTriangle,
    ShoppingBag
} from 'lucide-react';

export default function SidebarShell({
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
    console.log(settings);
    // Use settings.logo if passed, otherwise use tenant.logo from shared props
    const logo = settings?.logo || tenant?.logo;
    const storeName = settings?.name || tenant?.name || 'Tienda';
    // Fix: Access company_name safely if it exists on settings or tenant
    const companyName = (settings as any)?.company_name || (tenant as any)?.company_name || storeName;

    const navItems = [
        { name: 'Inicio', href: tRoute('dashboard'), icon: Home },
        { name: 'Inventario', href: tRoute('inventory.index'), icon: Package },
        { name: 'Punto de Venta', href: tRoute('pos.index'), icon: ShoppingCart },
        { name: 'Ventas', href: tRoute('sales.index'), icon: BarChart3 },
        { name: 'Capital', href: tRoute('capital.index'), icon: BarChart3 },
        { name: 'Caja', href: tRoute('cash-closings.index'), icon: DollarSign },
        { name: 'Pagos', href: tRoute('payments.index'), icon: Wallet },
        { name: 'Tareas', href: tRoute('tasks.index'), icon: ListTodo },
        { name: 'Consumo', href: tRoute('outputs.index'), icon: PackageMinus },
        { name: 'Mermas', href: tRoute('decrease.index'), icon: AlertTriangle },
        { name: 'Catálogo', href: tRoute('shop.index'), icon: ShoppingBag },
        { name: 'Horarios', href: tRoute('schedules.index'), icon: Calendar },
        { name: 'Configuración', href: tRoute('settings.index'), icon: Settings },
    ];

    return (
        <div className={`bg-gray-100 ${className}`}>
            {/* Mobile sidebar toggle */}
            <div className="lg:hidden fixed top-0 left-0 right-0 z-40 bg-white border-b h-14 flex items-center px-4">
                <button
                    onClick={() => setShowingNavigationDropdown(!showingNavigationDropdown)}
                    className="p-2 rounded-md text-gray-500 hover:bg-gray-100"
                >
                    {showingNavigationDropdown ? <X className="w-6 h-6" /> : <Menu className="w-6 h-6" />}
                </button>
                <div className="ml-3 flex-1">
                    {logo ? (
                        <img src={logo} alt={storeName} className="h-8 object-contain" />
                    ) : (
                        <ApplicationLogo className="h-8 w-auto fill-current text-gray-800" />
                    )}
                </div>
            </div>

            {/* Sidebar */}
            <aside className={`fixed inset-y-0 left-0 z-30 w-64 bg-white border-r transform transition-transform duration-300 lg:translate-x-0 ${showingNavigationDropdown ? 'translate-x-0' : '-translate-x-full'}`}>
                <div className="h-16 flex items-center px-6 border-b">
                    <Link href="/" className="flex items-center">
                        {logo ? (
                            <img src={logo} alt={storeName} className="h-8 object-contain" />
                        ) : (
                            <ApplicationLogo className="h-8 w-auto fill-current text-gray-800" />
                        )}
                    </Link>
                    <span className="ml-2">
                        {companyName}
                    </span>
                </div>

                <nav className="p-4 space-y-1">
                    {navItems.map((item) => {
                        const Icon = item.icon;
                        const isActive = window.location.pathname.includes(item.href.split('?')[0]);
                        return (
                            <Link
                                key={item.name}
                                href={item.href}
                                className={`flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-colors ${
                                    isActive
                                        ? 'bg-primary text-white'
                                        : 'text-gray-600 hover:bg-gray-100'
                                }`}
                            >
                                <Icon className="w-5 h-5" />
                                {item.name}
                            </Link>
                        );
                    })}
                </nav>

                <div className="absolute bottom-0 left-0 right-0 p-4 border-t">
                    <Dropdown>
                        <Dropdown.Trigger>
                            <button className="w-full flex items-center gap-3 px-4 py-3 rounded-lg text-sm hover:bg-gray-100">
                                <div className="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center text-primary font-semibold">
                                    {user.name.charAt(0)}
                                </div>
                                <div className="flex-1 text-left">
                                    <div className="font-medium text-gray-900">{user.name}</div>
                                    <div className="text-xs text-gray-500 truncate">{user.email}</div>
                                </div>
                            </button>
                        </Dropdown.Trigger>
                        <Dropdown.Content>
                            <Dropdown.Link href={route('profile.edit')}>Profile</Dropdown.Link>
                            <Dropdown.Link href={route('logout')} method="post" as="button">Log Out</Dropdown.Link>
                        </Dropdown.Content>
                    </Dropdown>
                </div>
            </aside>

            {/* Main content */}
            <div className="lg:ml-64 pt-14 lg:pt-0">
                {/* Top bar */}
                <header className="hidden lg:flex h-16 bg-white border-b items-center justify-between px-6">
                    <div className="flex-1" />
                    <div className="flex items-center gap-4">
                        <ThemeSwitcherCompact />
                    </div>
                </header>

                <main className={isFullMain ? 'h-[calc(100vh-4rem)]' : ''}>{children}</main>
                <Toaster />
            </div>

            {/* Mobile overlay */}
            {showingNavigationDropdown && (
                <div
                    className="fixed inset-0 bg-black/50 z-20 lg:hidden"
                    onClick={() => setShowingNavigationDropdown(false)}
                />
            )}
        </div>
    );
}
