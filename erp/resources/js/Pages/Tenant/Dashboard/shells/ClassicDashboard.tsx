import { Link, usePage } from '@inertiajs/react';
import { useTenantRoute } from '@/Hooks/useTenantRoute';
import { useMemo } from 'react';
import { DashboardProps, DashboardModule } from './types';

export default function ClassicDashboard({ modules }: DashboardProps) {
    const { tenant } = usePage().props as any;
    const tRoute = useTenantRoute();

    const ModuleCard = ({ module }: { module: DashboardModule }) => {
        const content = (
            <>
                <div className={`h-2 bg-linear-to-r ${module.color}`} />
                <div className="p-6 text-center">
                    <div className="text-4xl mb-3 flex items-center justify-center">{module.icon}</div>
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
        <>
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
        </>
    );
}
