import { Link, usePage } from '@inertiajs/react';
import { DashboardProps, DashboardModule } from './types';
import { ChevronRight } from 'lucide-react';
import RevenueChart from '@/Components/widgets/RevenueChart';
import StatsCards from '@/Components/widgets/StatsCards';
import TopProducts from '@/Components/widgets/TopProducts';
import StockAlerts from '@/Components/widgets/StockAlerts';
import RecentSales from '@/Components/widgets/RecentSales';
import SalesByCategory from '@/Components/widgets/SalesByCategory';
import SalesByTime from '@/Components/widgets/SalesByTime';
import ProfitMargin from '@/Components/widgets/ProfitMargin';
import CashStatus from '@/Components/widgets/CashStatus';
import Expenses from '@/Components/widgets/Expenses';

export default function SidebarDashboard({ modules }: DashboardProps) {
    const { tenant, auth } = usePage().props as any;

    // First 6 are pinned for quick access
    const pinnedModules = modules.slice(0, 6);
    const allModules = modules.slice(6);

    return (
        <div className="min-h-[calc(100vh-120px)] bg-white px-4 lg:px-8 py-10">
            {/* Header */}
            <div className="mb-6 flex items-start justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-foreground mb-1">
                        Panel de Control
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        Resumen de tu negocio
                    </p>
                </div>
                {auth?.user && (
                    <div className="bg-white border border-gray-200 rounded-full pl-1 pr-4 py-1 shadow-sm flex items-center gap-3">
                        <div className="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold text-xs">
                            {auth.user.name.split(' ').map((n: string) => n[0]).join('').substring(0, 2).toUpperCase()}
                        </div>
                        <div className="flex flex-col">
                            <span className="text-[10px] text-gray-400 font-medium uppercase tracking-wider">Responsable</span>
                            <span className="text-sm font-semibold text-gray-700 leading-tight">{auth.user.name}</span>
                        </div>
                        <div className="w-2 h-2 rounded-full bg-green-500 animate-pulse" title="Turno Activo"></div>
                    </div>
                )}
            </div>

            {/* Main Dashboard Grid */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                {/* Revenue Chart - Takes 2 columns */}
                <div className="lg:col-span-2">
                    <RevenueChart />
                </div>

                {/* Profit Margin - Premium widget */}
                <ProfitMargin enableGif={false} />
            </div>

            {/* Secondary Widgets Row */}
            <div className="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-6">
                 <div className="lg:col-span-2 flex flex-col gap-6">
                      <SalesByTime />
                       <SalesByCategory />
                </div>
                {/*<StatsCards /> */}
                <div className="flex flex-col gap-6">
                    <TopProducts />
                    <StockAlerts />
                </div>
                <RecentSales />

            </div>

            {/* Third Row - Charts */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                {/* Sales By Time Heatmap - Takes 2 columns */}
                <div className="lg:col-span-2">

                </div>

                {/* Sales By Category */}

            </div>

            {/* Fourth Row - Finance & Operations */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                {/* Cash Status */}
                <CashStatus />

                {/* Expenses */}
                <Expenses />
            </div>

            {/* Quick Access Grid */}
            <div className="mb-8">
                <h2 className="text-sm font-medium text-muted-foreground uppercase tracking-wider mb-4">
                    Acceso Rápido
                </h2>
                <div className="grid grid-cols-2 lg:grid-cols-3 gap-4">
                    {pinnedModules.map((module) => (
                        <Link
                            key={module.name}
                            href={module.href || '#'}
                            className={`
                                group relative bg-card rounded-2xl p-6
                                border border-border
                                hover:shadow-lg hover:border-primary/30
                                transition-all duration-200
                                ${module.soon ? 'opacity-50 pointer-events-none' : ''}
                            `}
                        >
                            {/* Icon */}
                            <div className="mb-4">
                                {typeof module.icon === 'string' ? (
                                    <span className="text-4xl">{module.icon}</span>
                                ) : (
                                    <div className="w-12 h-12">{module.icon}</div>
                                )}
                            </div>

                            {/* Content */}
                            <h3 className="font-semibold text-foreground mb-1 group-hover:text-primary transition-colors">
                                {module.name}
                            </h3>
                            <p className="text-sm text-muted-foreground line-clamp-2">
                                {module.description}
                            </p>

                            {/* Arrow */}
                            <div className="absolute top-4 right-4 opacity-0 group-hover:opacity-100 transition-opacity">
                                <ChevronRight className="w-5 h-5 text-muted-foreground" />
                            </div>

                            {module.soon && (
                                <span className="absolute top-2 right-2 text-xs bg-secondary text-muted-foreground px-2 py-0.5 rounded">
                                    Soon
                                </span>
                            )}
                        </Link>
                    ))}
                </div>
            </div>

            {/* Other modules list */}
            {allModules.length > 0 && (
                <div>
                    <h2 className="text-sm font-medium text-muted-foreground uppercase tracking-wider mb-4">
                        Más Módulos
                    </h2>
                    <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
                        {allModules.map((module) => (
                            <Link
                                key={module.name}
                                href={module.href || '#'}
                                className={`
                                    group flex items-center gap-3 p-3 bg-card rounded-xl
                                    border border-border hover:border-primary/30
                                    transition-all
                                    ${module.soon ? 'opacity-50 pointer-events-none' : ''}
                                `}
                            >
                                <div className="w-8 h-8 flex items-center justify-center">
                                    {typeof module.icon === 'string' ? (
                                        <span className="text-lg">{module.icon}</span>
                                    ) : (
                                        <div className="w-6 h-6">{module.icon}</div>
                                    )}
                                </div>
                                <span className="text-sm font-medium text-foreground group-hover:text-primary truncate">
                                    {module.name}
                                </span>
                            </Link>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
