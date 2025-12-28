import SuperAdminLayout from '@/Layouts/SuperAdminLayout';
import { Head, Link } from '@inertiajs/react';

interface Stats {
    totalTenants: number;
    activeTenants: number;
    suspendedTenants: number;
    totalUsers: number;
    superAdmins: number;
}

interface Tenant {
    id: number;
    name: string;
    slug: string;
    status: string;
    plan: string;
    created_at: string;
}

interface Props {
    stats: Stats;
    recentTenants: Tenant[];
}

export default function Dashboard({ stats, recentTenants }: Props) {
    const statCards = [
        {
            title: 'Tiendas Activas',
            value: stats.activeTenants,
            description: `de ${stats.totalTenants} totales`,
            color: 'bg-emerald-500',
            icon: '🏪',
        },
        {
            title: 'Usuarios en Tiendas',
            value: stats.totalUsers,
            description: 'usuarios registrados',
            color: 'bg-blue-500',
            icon: '👥',
        },
        {
            title: 'Super Admins',
            value: stats.superAdmins,
            description: 'administradores globales',
            color: 'bg-purple-500',
            icon: '🛡️',
        },
        {
            title: 'Tiendas Suspendidas',
            value: stats.suspendedTenants,
            description: 'requieren atención',
            color: 'bg-red-500',
            icon: '⚠️',
        },
    ];

    const getStatusBadge = (status: string) => {
        const styles: Record<string, string> = {
            active: 'bg-green-100 text-green-800',
            inactive: 'bg-gray-100 text-gray-800',
            suspended: 'bg-red-100 text-red-800',
        };
        const labels: Record<string, string> = {
            active: 'Activa',
            inactive: 'Inactiva',
            suspended: 'Suspendida',
        };
        return (
            <span className={`px-2 py-1 text-xs font-medium rounded-full ${styles[status]}`}>
                {labels[status]}
            </span>
        );
    };

    return (
        <SuperAdminLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Dashboard SuperAdmin
                </h2>
            }
        >
            <Head title="SuperAdmin Dashboard" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    {/* Stats Grid */}
                    <div className="grid grid-cols-1 gap-6 mb-8 sm:grid-cols-2 lg:grid-cols-4">
                        {statCards.map((stat, index) => (
                            <div
                                key={index}
                                className="overflow-hidden bg-white rounded-lg shadow"
                            >
                                <div className="p-5">
                                    <div className="flex items-center">
                                        <div className={`flex-shrink-0 p-3 rounded-md ${stat.color}`}>
                                            <span className="text-2xl">{stat.icon}</span>
                                        </div>
                                        <div className="ml-5 w-0 flex-1">
                                            <dl>
                                                <dt className="text-sm font-medium text-gray-500 truncate">
                                                    {stat.title}
                                                </dt>
                                                <dd className="flex items-baseline">
                                                    <div className="text-2xl font-semibold text-gray-900">
                                                        {stat.value}
                                                    </div>
                                                </dd>
                                                <dd className="text-sm text-gray-500">
                                                    {stat.description}
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>

                    {/* Recent Tenants */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <div className="flex items-center justify-between mb-4">
                                <h3 className="text-lg font-semibold text-gray-900">
                                    Tiendas Recientes
                                </h3>
                                <Link
                                    href={route('superadmin.tenants.index')}
                                    className="text-sm text-indigo-600 hover:text-indigo-900"
                                >
                                    Ver todas →
                                </Link>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Tienda
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Estado
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Plan
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Creada
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {recentTenants.map((tenant) => (
                                            <tr key={tenant.id}>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="text-sm font-medium text-gray-900">
                                                        {tenant.name}
                                                    </div>
                                                    <div className="text-sm text-gray-500">
                                                        {tenant.slug}
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    {getStatusBadge(tenant.status)}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 capitalize">
                                                    {tenant.plan}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {new Date(tenant.created_at).toLocaleDateString()}
                                                </td>
                                            </tr>
                                        ))}
                                        {recentTenants.length === 0 && (
                                            <tr>
                                                <td colSpan={4} className="px-6 py-4 text-center text-gray-500">
                                                    No hay tiendas creadas aún.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>

                            <div className="mt-4">
                                <Link
                                    href={route('superadmin.tenants.create')}
                                    className="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                >
                                    + Nueva Tienda
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </SuperAdminLayout>
    );
}
