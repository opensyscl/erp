import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { useTenantRoute } from '@/Hooks/useTenantRoute';
import { useMemo } from 'react';

export default function Dashboard() {
    // Get tenant from shared props
    const { tenant } = usePage().props as any;
    const tRoute = useTenantRoute();

    const modules = useMemo(() => [
        {
            name: 'Inventario',
            description: 'Gestiona tus productos y analiza su rendimiento',
            icon: '📦',
            href: tRoute('inventory.index'),
            color: 'from-blue-500 to-blue-600',
        },
        {
            name: 'Punto de Venta',
            description: 'Realiza ventas rápidas y gestiona la caja',
            icon: '🛒',
            href: tRoute('pos.index'),
            color: 'from-green-500 to-green-600',
        },
        {
            name: 'Compras',
            description: 'Registra facturas de proveedores y controla costos',
            icon: '📥',
            href: tRoute('purchases.index'),
            color: 'from-amber-500 to-amber-600',
        },
        {
            name: 'Ventas',
            description: 'Historial de ventas y reportes',
            icon: '📊',
            href: tRoute('sales.index'),
            color: 'from-purple-500 to-purple-600',
        },
        {
            name: 'Horarios',
            description: 'Gestiona turnos y horarios de empleados',
            icon: '📅',
            href: tRoute('schedules.index'),
            color: 'from-pink-500 to-pink-600',
        },
        {
            name: 'Clientes',
            description: 'Gestiona tu base de clientes',
            icon: '👥',
            href: null,
            color: 'from-orange-500 to-orange-600',
            soon: true,
        },
        {
            name: 'Configuración',
            description: 'Personaliza los colores y datos de tu tienda',
            icon: '⚙️',
            href: tRoute('settings.index'),
            color: 'from-gray-500 to-gray-600',
        },
    ], [tenant, tRoute]);

    const ModuleCard = ({ module }: { module: typeof modules[0] }) => {
        const content = (
            <>
                <div className={`h-2 bg-linear-to-r ${module.color}`} />
                <div className="p-6 text-center">
                    <div className="text-4xl mb-3">{module.icon}</div>
                    <h3 className="font-semibold text-gray-900 mb-2">
                        {module.name}
                        {module.soon && <span className="ml-2 text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded">Próximamente</span>}
                    </h3>
                    <p className="text-sm text-gray-500">{module.description}</p>
                    {!module.soon && (
                        <div className="mt-4 text-blue-600 text-sm font-medium group-hover:underline">
                            Ir a {module.name} →
                        </div>
                    )}
                </div>
            </>
        );

        if (module.soon || !module.href) {
            return (
                <div className="bg-white rounded-lg shadow-sm overflow-hidden opacity-60 cursor-not-allowed">
                    {content}
                </div>
            );
        }

        return (
            <Link
                href={module.href}
                className="bg-white rounded-lg shadow-sm overflow-hidden hover:shadow-md transition group"
            >
                {content}
            </Link>
        );
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Dashboard de Tienda
                </h2>
            }
        >
            <Head title="Dashboard" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    {/* Welcome Card */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8">
                        <div className="p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-2">
                                ¡Bienvenido a tu tienda!
                            </h3>
                            <p className="text-gray-600">
                                Gestiona tu inventario, realiza ventas y analiza el rendimiento de tu negocio.
                            </p>
                        </div>
                    </div>

                    {/* Modules Grid */}
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        {modules.map((module) => (
                            <ModuleCard key={module.name} module={module} />
                        ))}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
