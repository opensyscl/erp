import { useState, PropsWithChildren, ReactNode } from 'react';
import '../../css/superadmin.css'; // Importar estilos específicos del admin
import { Link, usePage } from '@inertiajs/react';
import {
    LayoutDashboard,
    Building2,
    Settings,
    LogOut,
    Menu,
    X,
    User,
    ChevronLeft,
    ChevronRight
} from 'lucide-react';
import { PageProps } from '@/types';
import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';

export default function SuperAdminLayout({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const user = usePage<PageProps>().props.auth.user;
    const [sidebarOpen, setSidebarOpen] = useState(false); // Mobile
    const [isCollapsed, setIsCollapsed] = useState(false); // Desktop

    const navigation = [
        { name: 'Dashboard', href: route('superadmin.dashboard'), icon: LayoutDashboard, current: route().current('superadmin.dashboard') },
        { name: 'Tenants', href: route('superadmin.tenants.index'), icon: Building2, current: route().current('superadmin.tenants.*') },
        // { name: 'Configuración', href: '#', icon: Settings, current: false },
    ];

    return (
        <div className="flex h-screen bg-sa-bg font-sans text-sa-text overflow-hidden">
            {/* Sidebar Mobile Overlay */}
            {sidebarOpen && (
                <div
                    className="fixed inset-0 z-40 bg-gray-600 bg-opacity-50 md:hidden backdrop-blur-sm"
                    onClick={() => setSidebarOpen(false)}
                ></div>
            )}

            {/* Sidebar */}
            <div className={`
                fixed inset-y-0 left-0 z-50 bg-sa-sidebar border-r border-sa-border transition-all duration-300 ease-in-out shrink-0 flex flex-col
                ${sidebarOpen ? 'translate-x-0' : '-translate-x-full'}
                md:relative md:translate-x-0
                ${isCollapsed ? 'w-20' : 'w-64'}
                ${sidebarOpen ? 'w-64' : ''}
            `}>
                {/* Desktop Collapse Toggle (Absolute on border) */}
                <button
                    onClick={() => setIsCollapsed(!isCollapsed)}
                    className="hidden md:flex absolute -right-3 top-9 z-50 bg-white border border-sa-border text-gray-500 hover:text-indigo-600 rounded-full w-6 h-6 items-center justify-center shadow-sm transition-colors"
                >
                    {isCollapsed ? <ChevronRight size={14} /> : <ChevronLeft size={14} />}
                </button>

                {/* Logo Area */}
                <div className={`h-20 flex items-center ${isCollapsed ? 'justify-center px-0' : 'px-8'} border-b border-gray-50/50 transition-all duration-300`}>
                    <div className="flex items-center gap-2">
                        <div className="w-8 h-8 bg-sa-accent rounded-lg flex items-center justify-center text-white shadow-lg shadow-indigo-500/30 shrink-0">
                            <span className="font-bold text-lg">O</span>
                        </div>
                        <div className={`overflow-hidden transition-all duration-300 ${isCollapsed ? 'w-0 opacity-0' : 'w-auto opacity-100'}`}>
                            <span className="font-bold text-xl tracking-tight text-gray-800 whitespace-nowrap">
                                Open<span className="text-sa-accent">Sys</span>
                            </span>
                        </div>
                    </div>
                    {/* Close button for mobile */}
                    <button
                        className="md:hidden ml-auto text-gray-400"
                        onClick={() => setSidebarOpen(false)}
                    >
                        <X className="w-5 h-5" />
                    </button>
                </div>

                {/* Navigation Items */}
                <div className="p-3 space-y-2 flex-1 overflow-y-auto mt-2">
                    {navigation.map((item) => {
                        const isActive = item.current;
                        return (
                            <Link
                                key={item.name}
                                href={item.href}
                                title={isCollapsed ? item.name : ''}
                                className={`
                                    relative group flex items-center ${isCollapsed ? 'justify-center' : ''} gap-3 px-2 py-2 rounded-xl transition-all duration-200 font-medium
                                    ${isActive
                                        ? 'bg-indigo-50 text-primary'
                                        : 'text-gray-500 hover:bg-gray-50 hover:text-gray-700'
                                    }
                                `}
                            >
                                {isActive && (
                                    <div className={`absolute left-0 top-1/2 -translate-y-1/2 w-1 h-4 bg-primary rounded-r-full transition-all duration-300 ${isCollapsed ? 'h-3' : 'h-4'}`} />
                                )}

                                <item.icon className={`w-5 h-5 shrink-0  transition-colors ${isActive ? 'text-primary ml-3' : 'text-gray-400 ml-3 group-hover:text-gray-500'}`} />

                                <span className={`whitespace-nowrap overflow-hidden text-xs transition-all duration-300 ${isCollapsed ? 'w-0 opacity-0' : 'w-auto opacity-100'}`}>
                                    {item.name}
                                </span>
                            </Link>
                        );
                    })}
                </div>

                {/* Bottom Profile Section */}
                <div className="p-4 border-t border-sa-border">
                     <div className={`flex items-center gap-3 p-2 rounded-xl hover:bg-gray-50 cursor-pointer transition group ${isCollapsed ? 'justify-center' : ''}`}>
                        <div className="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-bold border-2 border-white shadow-sm shrink-0">
                            {user.name.charAt(0)}
                        </div>

                        <div className={`overflow-hidden transition-all duration-300 ${isCollapsed ? 'w-0 opacity-0 hidden' : 'w-auto opacity-100'}`}>
                            <p className="text-sm font-semibold text-gray-700 truncate group-hover:text-indigo-600 transition">{user.name}</p>
                            <p className="text-xs text-gray-400 truncate">Super Admin</p>
                        </div>

                        <div className={`ml-auto transition-all duration-300 ${isCollapsed ? 'w-0 opacity-0 hidden' : 'w-auto opacity-100'}`}>
                             <Link href={route('logout')} method="post" as="button" className="text-gray-400 hover:text-red-500 transition p-1">
                                <LogOut className="w-4 h-4" />
                             </Link>
                        </div>
                     </div>
                </div>
            </div>

            {/* Main Content Area */}
            <div className="flex-1 flex flex-col w-0 overflow-hidden bg-sa-bg">
                {/* Minimal Topbar Mobile Only */}
                <div className="md:hidden h-16 bg-white border-b border-sa-border flex items-center justify-between px-4 shrink-0 transition-all">
                     <div className="flex items-center gap-2">
                        <div className="w-8 h-8 bg-sa-accent rounded-lg flex items-center justify-center text-white">
                            <span className="font-bold">O</span>
                        </div>
                        <span className="font-bold text-gray-800">OpenSys</span>
                     </div>
                     <button onClick={() => setSidebarOpen(!sidebarOpen)} className="p-2 text-gray-500 hover:bg-gray-100 rounded-lg">
                        <Menu className="w-6 h-6" />
                     </button>
                </div>

                {/* Page Content */}
                <main className="flex-1 overflow-y-auto focus:outline-none p-4 md:p-8">
                     <div className="max-w-7xl mx-auto h-full flex flex-col">
                        {header && (
                            <header className="mb-8 shrink-0">
                                <h1 className="text-2xl font-bold text-gray-800 tracking-tight">
                                    {header}
                                </h1>
                            </header>
                        )}
                        <div className="flex-1">
                            {children}
                        </div>
                     </div>
                </main>
            </div>
        </div>
    );
}
