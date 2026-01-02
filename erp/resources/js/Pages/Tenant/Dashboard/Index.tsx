import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage } from '@inertiajs/react';
import { useTenantRoute } from '@/Hooks/useTenantRoute';
import { useMemo } from 'react';
import { DashboardModule, DashboardShellType } from './shells/types';

// Import shells
import ClassicDashboard from './shells/ClassicDashboard';
import ModernDashboard from './shells/ModernDashboard';
import MinimalDashboard from './shells/MinimalDashboard';
import DarkDashboard from './shells/DarkDashboard';
import SidebarDashboard from './shells/SidebarDashboard';



const DASHBOARD_SHELLS = {
    classic: ClassicDashboard,
    modern: ModernDashboard,
    minimal: MinimalDashboard,
    dark: DarkDashboard,
    sidebar: SidebarDashboard,
} as const;

export default function Dashboard() {
    const { tenant, settings } = usePage().props as any;
    const tRoute = useTenantRoute();

    // Get dashboard shell preference from tenant settings
    const dashboardShell: DashboardShellType = settings?.dashboard_shell || 'classic';

    // Get the shell component
    const ShellComponent = DASHBOARD_SHELLS[dashboardShell] || ClassicDashboard;
    console.log(dashboardShell);
    const modules: DashboardModule[] = useMemo(() => [
        // === ACTIVOS ===
        {
            name: 'Punto de Venta',
            description: 'Vende en tu tienda fácilmente',
            icon: (
                <div className="w-20 h-20 rounded-3xl bg-white border border-gray-200 p-1.5 flex items-center justify-center">
                    <img src="/iso/pos.png" alt="POS" className="w-full h-full object-contain" />
                </div>
            ),
            href: tRoute('pos.index'),
            color: 'from-green-500 to-green-600',
        },
        {
            name: 'Ventas',
            description: 'Analiza ventas de productos, proveedores y categorías',
            icon: (
                <div className="w-20 h-20 rounded-3xl bg-white border border-gray-200 p-1.5 flex items-center justify-center">
                    <img src="/iso/ventas.png" alt="Ventas" className="w-full h-full object-contain" />
                </div>
            ),
            href: tRoute('sales.index'),
            color: 'from-purple-500 to-purple-600',
        },
        {
            name: 'Inventario',
            description: 'Gestiona tus productos y analiza su rendimiento',
            icon: (
                <div className="w-20 h-20 rounded-3xl bg-white border border-gray-200 p-1.5 flex items-center justify-center">
                    <img src="/iso/inventario.png" alt="Inventario" className="w-full h-full object-contain" />
                </div>
            ),
            href: tRoute('inventory.index'),
            color: 'from-blue-500 to-blue-600',
        },
        {
            name: 'Compras',
            description: 'Ingresa facturas de tus proveedores',
            icon: (
                <div className="w-20 h-20 rounded-3xl bg-white border border-gray-200 p-1.5 flex items-center justify-center">
                    <img src="/iso/compras.png" alt="Compras" className="w-full h-full object-contain" />
                </div>
            ),
            href: tRoute('purchases.index'),
            color: 'from-amber-500 to-amber-600',
        },
        {
            name: 'Horarios y Turnos',
            description: 'Gestión de horarios de empleados y turnos de caja',
            icon: (
                <div className="w-20 h-20 rounded-3xl bg-white border border-gray-200 p-1.5 flex items-center justify-center">
                    <img src="/iso/horarios.png" alt="Horarios" className="w-full h-full object-contain" />
                </div>
            ),
            href: tRoute('schedules.index'),
            color: 'from-pink-500 to-pink-600',
        },
        {
            name: 'Configuración',
            description: 'Personaliza los colores y datos de tu tienda',
            icon: (
                <div className="w-20 h-20 rounded-3xl bg-white border border-gray-200 p-1.5 flex items-center justify-center">
                    <img src="/iso/configuracion.png" alt="Configuración" className="w-full h-full object-contain" />
                </div>
            ),
            href: tRoute('settings.index'),
            color: 'from-gray-500 to-gray-600',
        },
        // === OTROS MÓDULOS ===
        {
            name: 'Packs y Promos',
            description: 'Crea y gestiona ofertas para impulsar ventas',
            icon: (
                <div className="w-20 h-20 rounded-3xl bg-white border border-gray-200 p-1.5 flex items-center justify-center">
                    <img src="/iso/packs.png" alt="Packs" className="w-full h-full object-contain" />
                </div>
            ),
            href: tRoute('offers.index'),
            color: 'from-red-500 to-red-600',
        },
        {
            name: 'Ranking de Productos',
            description: 'Identifica productos estrella y de menor rotación',
            icon: (
                <div className="w-20 h-20 rounded-3xl bg-white border border-gray-200 p-1.5 flex items-center justify-center">
                    <img src="/iso/ranking.png" alt="Ranking" className="w-full h-full object-contain" />
                </div>
            ),
            href: tRoute('ranking.index'),
            color: 'from-yellow-500 to-yellow-600',
        },
        {
            name: 'Centro de Etiquetas',
            description: 'Imprime etiquetas de precios con un solo clic',
            icon: (
                <div className="w-20 h-20 rounded-3xl bg-white border border-gray-200 p-1.5 flex items-center justify-center">
                    <img src="/iso/etiquetas.png" alt="Etiquetas" className="w-full h-full object-contain" />
                </div>
            ),
            href: tRoute('labels.index'),
            color: 'from-cyan-500 to-cyan-600',
        },
        {
            name: 'Gestión de Terceros',
            description: 'Gestiona tus Clientes y Proveedores',
            icon: (
                <div className="w-20 h-20 rounded-3xl bg-white border border-gray-200 p-1.5 flex items-center justify-center">
                    <img src="/iso/gestiondeterceros.png" alt="Terceros" className="w-full h-full object-contain" />
                </div>
            ),
            href: tRoute('terceros.index'),
            color: 'from-orange-500 to-orange-600',
        },
        {
            name: 'Cotizaciones',
            description: 'Genera cotizaciones a tus proveedores',
            icon: (
                <div className="w-20 h-20 rounded-3xl bg-white border border-gray-200 p-1.5 flex items-center justify-center">
                    <img src="/iso/cotizaciones.png" alt="Cotizaciones" className="w-full h-full object-contain" />
                </div>
            ),
            href: tRoute('quotations.index'),
            color: 'from-indigo-500 to-indigo-600',
        },
        {
            name: 'Pedidos',
            description: 'Crea pedidos a tus proveedores',
            icon: (
                <div className="w-20 h-20 rounded-3xl bg-white border border-gray-200 p-1.5 flex items-center justify-center">
                    <img src="/iso/pedidos.png" alt="Pedidos" className="w-full h-full object-contain" />
                </div>
            ),
            href: tRoute('orders.index'),
            color: 'from-teal-500 to-teal-600',
        },
        {
            name: 'Análisis de Capital',
            description: 'Visualiza la estructura de tu capital y toma decisiones',
            icon: (
                <div className="w-20 h-20 rounded-3xl bg-white border border-gray-200 p-1.5 flex items-center justify-center">
                    <img src="/iso/capital.png" alt="Capital" className="w-full h-full object-contain" />
                </div>
            ),
            href: tRoute('capital.index'),
            color: 'from-violet-500 to-violet-600',
        },
        {
            name: 'Cuadres de Caja',
            description: 'Gestiona el flujo de efectivo diario',
            icon: (
                <div className="w-20 h-20 rounded-3xl bg-white border border-gray-200 p-1.5 flex items-center justify-center">
                    <img src="/iso/caja.png" alt="Cuadres" className="w-full h-full object-contain" />
                </div>
            ),
            href: tRoute('cash-closings.index'),
            color: 'from-emerald-500 to-emerald-600',
        },
        {
            name: 'Pagos a Proveedores',
            description: 'Gestiona facturas pendientes y pagos',
            icon: (
                <div className="w-20 h-20 rounded-3xl bg-white border border-gray-200 p-1.5 flex items-center justify-center">
                    <img src="/iso/pago-proveedores.png" alt="Pagos" className="w-full h-full object-contain" />
                </div>
            ),
            href: tRoute('payments.index'),
            color: 'from-blue-500 to-blue-600',
        },
        {
            name: 'Gastos Operativos',
            description: 'Registra y categoriza todos los gastos de operación',
            icon: (
                <div className="w-20 h-20 rounded-3xl bg-white border border-gray-200 p-1.5 flex items-center justify-center">
                    <img src="/iso/gastos-operativos.png" alt="Gastos" className="w-full h-full object-contain" />
                </div>
            ),
            href: tRoute('expenses.index'),
            color: 'from-fuchsia-500 to-fuchsia-600',
        },
        {
            name: 'Consumo Interno',
            description: 'Gestiona productos para uso interno',
            icon: (
                <div className="w-20 h-20 rounded-3xl bg-white border border-gray-200 p-1.5 flex items-center justify-center">
                    <img src="/iso/consumo-interno.png" alt="Consumo" className="w-full h-full object-contain" />
                </div>
            ),
            href: tRoute('outputs.index'),
            color: 'from-zinc-500 to-zinc-600',
        },
        {
            name: 'Centro de Tareas',
            description: 'Gestiona y organiza tus tareas pendientes',
            icon: (
                <div className="w-20 h-20 rounded-3xl bg-white border border-gray-200 p-1.5 flex items-center justify-center">
                    <img src="/iso/centro-tareas.png" alt="Tareas" className="w-full h-full object-contain" />
                </div>
            ),
            href: tRoute('tasks.index'),
            color: 'from-sky-500 to-sky-600',
        },
        {
            name: 'Registro de Asistencias',
            description: 'Registra y gestiona la asistencia de tu equipo',
            icon: (
                <div className="w-20 h-20 rounded-3xl bg-white border border-gray-200 p-1.5 flex items-center justify-center">
                    <img src="/iso/registro-de-asistencias.png" alt="Asistencias" className="w-full h-full object-contain" />
                </div>
            ),
            href: tRoute('attendance.index'),
            color: 'from-lime-500 to-lime-600',
        },
        {
            name: 'Mermas y Devoluciones',
            description: 'Gestiona productos dañados o vencidos',
            icon: (
                <div className="w-20 h-20 rounded-3xl bg-white border border-gray-200 p-1.5 flex items-center justify-center">
                    <img src="/iso/mermas.png" alt="Mermas" className="w-full h-full object-contain" />
                </div>
            ),
            href: tRoute('decrease.index'),
            color: 'from-amber-500 to-amber-600',
        },
        {
            name: 'Catálogo de Productos',
            description: 'Explora todos los productos de tu tienda',
            icon: (
                <div className="w-20 h-20 rounded-3xl bg-white border border-gray-200 p-1.5 flex items-center justify-center">
                    <img src="/iso/catalogo.png" alt="Catalogo" className="w-full h-full object-contain" />
                </div>
            ),
            href: tRoute('shop.index'),
            color: 'from-pink-500 to-rose-600',
        },
    ], [tenant, tRoute]);

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Dashboard de Tienda
                </h2>
            }
        >
            <Head title="Dashboard" />

            <ShellComponent modules={modules} />
        </AuthenticatedLayout>
    );
}
