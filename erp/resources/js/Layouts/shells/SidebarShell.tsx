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

    const navGroups = [
        {
            label: 'Principal',
            items: [
                { name: 'Inicio', href: tRoute('dashboard'), icon: Home },
            ]
        },
        {
            label: 'Ventas',
            items: [
                { name: 'Punto de Venta', href: tRoute('pos.index'), icon: ShoppingCart },
                { name: 'Ventas', href: tRoute('sales.index'), icon: BarChart3 },
                { name: 'Caja', href: tRoute('cash-closings.index'), icon: DollarSign },
            ]
        },
        {
            label: 'Inventario',
            items: [
                { name: 'Inventario', href: tRoute('inventory.index'), icon: Package },
                { name: 'Consumo', href: tRoute('outputs.index'), icon: PackageMinus },
                { name: 'Mermas', href: tRoute('decrease.index'), icon: AlertTriangle },
                { name: 'Catálogo', href: tRoute('shop.index'), icon: ShoppingBag },
            ]
        },
        {
            label: 'Finanzas',
            items: [
                { name: 'Capital', href: tRoute('capital.index'), icon: BarChart3 },
                { name: 'Pagos', href: tRoute('payments.index'), icon: Wallet },
            ]
        },
        {
            label: 'Gestión',
            items: [
                { name: 'Tareas', href: tRoute('tasks.index'), icon: ListTodo },
                { name: 'Horarios', href: tRoute('schedules.index'), icon: Calendar },
            ]
        },
        {
            label: 'Sistema',
            items: [
                { name: 'Configuración', href: tRoute('settings.index'), icon: Settings },
            ]
        },
    ];

    return (
        <div className={`bg-background ${className}`}>
            {/* Mobile sidebar toggle */}
            <div className="lg:hidden fixed top-0 left-0 right-0 z-40 bg-card border-b border-border h-14 flex items-center px-4">
                <button
                    onClick={() => setShowingNavigationDropdown(!showingNavigationDropdown)}
                    className="p-2 rounded-md text-muted-foreground hover:bg-secondary"
                >
                    {showingNavigationDropdown ? <X className="w-6 h-6" /> : <Menu className="w-6 h-6" />}
                </button>
                <div className="ml-3 flex-1">
                    {logo ? (
                        <img src={logo} alt={storeName} className="h-8 object-contain" />
                    ) : (
                        <ApplicationLogo className="h-8 w-auto fill-current text-foreground" />
                    )}
                </div>
            </div>

            {/* Sidebar */}
            <aside className={`fixed inset-y-0 left-0 z-30 w-64 bg-white border-r border transform transition-transform duration-300 lg:translate-x-0 ${showingNavigationDropdown ? 'translate-x-0' : '-translate-x-full'}`}>
                {/* Logo Header */}
                <div className="h-16 flex items-center px-6 border-b border-sidebar-border">
                    <Link href="/" className="flex items-center gap-2">
                        {logo ? (
                            <img src={logo} alt={storeName} className="h-8 object-contain" />
                        ) : (
                            <ApplicationLogo className="h-8 w-auto fill-current text-sidebar-foreground" />
                        )}
                        <span className="font-semibold text-sidebar-foreground truncate">
                            {companyName}
                        </span>
                    </Link>
                </div>

                {/* Categorized Navigation */}
                <nav className="p-3 space-y-4 overflow-y-auto max-h-[calc(100vh-8rem)]">
                    {navGroups.map((group) => (
                        <div key={group.label}>
                            <p className="text-xs font-medium text-muted-foreground uppercase tracking-wider px-3 mb-2">
                                {group.label}
                            </p>
                            <div className="space-y-0.5">
                                {group.items.map((item) => {
                                    const Icon = item.icon;
                                    const isActive = window.location.pathname.includes(item.href.split('?')[0]);
                                    return (
                                        <Link
                                            key={item.name}
                                            href={item.href}
                                            className={`flex relative items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors ${
                                                isActive
                                                    ? 'bg-link-sidebar text-muted-500'
                                                    : 'text-sidebar-foreground '
                                            }`}
                                        >
                                            {isActive && (
                                                <>
                                                   <div className="absolute top-1/2 h-5 w-1 origin-left -translate-y-1/2 rounded-r-full bg-primary transition duration-200 ease-out -left-5 scale-100 left-[-12px]"></div>
                                                </>
                                            )}
                                            <Icon className={`w-4 h-4 ${isActive ? 'text-primary' : 'text-sidebar-foreground'}`} />
                                            <span className=" text-label-sm" style={{color: 'hsl(0 0% 36.08%)'}}>{item.name}</span>
                                            {isActive && (
                                                <div className='ml-auto'>
                                                    <svg  viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" className="remixicon size-5 text-text-sub-600"><path d="M13.1717 12.0007L8.22192 7.05093L9.63614 5.63672L16.0001 12.0007L9.63614 18.3646L8.22192 16.9504L13.1717 12.0007Z"></path></svg>
                                                </div>
                                            )}
                                        </Link>
                                    );
                                })}
                            </div>
                        </div>
                    ))}
                </nav>

                <div className="absolute bottom-0 left-0 right-0 p-4 border-t border-sidebar-border bg-sidebar">
                    <Dropdown>
                        <Dropdown.Trigger>
                            <button className="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm hover:bg-sidebar-accent transition-colors">
                                <div className="w-8 h-8 rounded-full bg-sidebar-primary/10 flex items-center justify-center text-sidebar-primary font-semibold">
                                    {user.name.charAt(0)}
                                </div>
                                <div className="flex-1 text-left">
                                    <div className="font-medium text-sidebar-foreground">{user.name}</div>
                                    <div className="text-xs text-muted-foreground truncate">{user.email}</div>
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
               {/* <header className="hidden lg:flex h-16 bg-white border-b items-center justify-between px-6">
                    <div className="flex-1" />
                    <div className="flex items-center gap-4">
                        <ThemeSwitcherCompact />
                    </div>
                </header> */}

                <main className={isFullMain ? 'h-[calc(100vh-0rem)]' : ''}>{children}</main>
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
