import SuperAdminLayout from '@/Layouts/SuperAdminLayout';
import { Switch } from '@/components/ui/switch';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

interface Tenant {
    id: number;
    name: string;
    slug: string;
    domain: string | null;
    status: string;
    plan: string;
    users_count: number;
    created_at: string;
}

interface PaginatedData {
    data: Tenant[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}

interface Props {
    tenants: PaginatedData;
    filters: {
        search?: string;
        status?: string;
        plan?: string;
    };
}

export default function Index({ tenants, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || '');
    const [plan, setPlan] = useState(filters.plan || '');

    const handleFilter = () => {
        router.get(route('superadmin.tenants.index'), {
            search: search || undefined,
            status: status || undefined,
            plan: plan || undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleToggleStatus = (tenant: Tenant) => {
        if (confirm(`¿Estás seguro de ${tenant.status === 'active' ? 'suspender' : 'activar'} "${tenant.name}"?`)) {
            router.post(route('superadmin.tenants.toggle-status', tenant.id));
        }
    };

    const handleDelete = (tenant: Tenant) => {
        if (confirm(`¿Estás seguro de eliminar "${tenant.name}"? Esta acción no se puede deshacer.`)) {
            router.delete(route('superadmin.tenants.destroy', tenant.id));
        }
    };

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

    const getPlanBadge = (plan: string) => {
        const styles: Record<string, string> = {
            free: 'bg-gray-100 text-gray-800',
            basic: 'bg-blue-100 text-blue-800',
            pro: 'bg-indigo-100 text-indigo-800',
            enterprise: 'bg-purple-100 text-purple-800',
        };
        const labels: Record<string, string> = {
            free: 'Gratis',
            basic: 'Básico',
            pro: 'Pro',
            enterprise: 'Empresa',
        };
        return (
            <span className={`px-2 py-1 text-xs font-medium rounded-full ${styles[plan]}`}>
                {labels[plan]}
            </span>
        );
    };

    return (
        <SuperAdminLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Gestión de Tiendas
                    </h2>

                    <Link
                        href={route('superadmin.tenants.create')}
                        className="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700"
                    >
                        + Nueva Tienda
                    </Link>
                </div>
            }
        >
            <Head title="Tiendas" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            {/* Filters */}
                            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-4">
                                <input
                                    type="text"
                                    placeholder="Buscar por nombre o slug..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    onKeyDown={(e) => e.key === 'Enter' && handleFilter()}
                                    className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                                <select
                                    value={status}
                                    onChange={(e) => setStatus(e.target.value)}
                                    className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">Todos los estados</option>
                                    <option value="active">Activas</option>
                                    <option value="inactive">Inactivas</option>
                                    <option value="suspended">Suspendidas</option>
                                </select>
                                <select
                                    value={plan}
                                    onChange={(e) => setPlan(e.target.value)}
                                    className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">Todos los planes</option>
                                    <option value="free">Gratis</option>
                                    <option value="basic">Básico</option>
                                    <option value="pro">Pro</option>
                                    <option value="enterprise">Empresa</option>
                                </select>
                                <button
                                    onClick={handleFilter}
                                    className="inline-flex items-center justify-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700"
                                >
                                    Filtrar
                                </button>
                            </div>

                            {/* Table */}
                            <div className="overflow-x-auto">
                                <table className="table-auto w-full border-separate border-spacing-y-1">
                                    <thead className="bg-transparent">
                                        <tr>
                                            <th className="rounded-[42px] px-3 flex items-center justify-between">
                                                <div className="text-start px-0 py-1 w-1/4 min-w-[250px] max-w-[300px] text-sm sm:text-xs md:text-sm lg:text-base">Tenant Details</div>
                                                <div className="px-2 py-1 w-1/6 min-w-[80px] max-w-[120px] text-sm sm:text-xs md:text-sm lg:text-base">Status</div>
                                                <div className="px-2 py-1 w-1/6 min-w-[150px] max-w-[200px] text-sm sm:text-xs md:text-sm lg:text-base">Subscription Start</div>
                                                <div className="px-2 py-1 w-1/6 min-w-[150px] max-w-[200px] text-sm sm:text-xs md:text-sm lg:text-base">Subscription End</div>
                                                <div className="px-2 py-1 w-1/6 min-w-[80px] max-w-[120px] text-sm sm:text-xs md:text-sm lg:text-base">Plan</div>
                                                <div className="px-2 py-1 w-1/5 min-w-[80px] max-w-[200px] text-sm sm:text-xs md:text-sm lg:text-base mr-8 text-right">Actions</div>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {tenants.data.map((tenant) => (
                                            <tr key={tenant.id}>
                                                <td className="py-1">
                                                    <div className="bg-gray-100 rounded-[42px] px-3 flex items-center justify-between">
                                                        {/* Tenant Details */}
                                                        <div className="flex items-center py-2 w-1/4 min-w-[250px] max-w-[300px]">
                                                            <div className="rounded-[42px] p-6 mr-4 bg-white/90 shadow-sm flex items-center justify-center">
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" className="tabler-icon tabler-icon-building-store text-gray-700">
                                                                    <path d="M3 21l18 0"></path>
                                                                    <path d="M3 7v1a3 3 0 0 0 6 0v-1m0 1a3 3 0 0 0 6 0v-1m0 1a3 3 0 0 0 6 0v-1h-18l2 -4h14l2 4"></path>
                                                                    <path d="M5 21l0 -10.15"></path>
                                                                    <path d="M19 21l0 -10.15"></path>
                                                                    <path d="M9 21v-4a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v4"></path>
                                                                </svg>
                                                            </div>
                                                            <div>
                                                                <div className="font-bold text-sm text-gray-900">{tenant.name}</div>
                                                                <div className="text-xs font-semibold text-gray-500">{tenant.slug}</div>
                                                            </div>
                                                        </div>

                                                        {/* Status */}
                                                        <div className="w-1/4 min-w-[80px] max-w-[120px] flex items-center justify-center">
                                                            <button
                                                                onClick={() => handleToggleStatus(tenant)}
                                                                className={`px-4 py-2 text-sm rounded-[42px] bg-white shadow-sm flex items-center gap-2 transition-colors ${
                                                                    tenant.status === 'active' ? 'text-green-600' : 'text-red-500'
                                                                }`}
                                                            >
                                                                {tenant.status === 'active' ? (
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" className="tabler-icon tabler-icon-check">
                                                                        <path d="M5 12l5 5l10 -10"></path>
                                                                    </svg>
                                                                ) : (
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" className="tabler-icon tabler-icon-ban">
                                                                        <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"></path>
                                                                        <path d="M5.7 5.7l12.6 12.6"></path>
                                                                    </svg>
                                                                )}
                                                                {tenant.status === 'active' ? 'Active' : 'Inactive'}
                                                            </button>
                                                        </div>

                                                        {/* Subscription Start */}
                                                        <div className="w-1/4 min-w-[150px] max-w-[200px] text-center text-sm font-bold text-gray-700">
                                                            {new Date(tenant.created_at).toLocaleDateString()}
                                                        </div>

                                                        {/* Subscription End */}
                                                        <div className="w-1/4 min-w-[150px] max-w-[200px] text-center text-sm font-bold text-gray-700">
                                                            -
                                                        </div>

                                                        {/* Plan */}
                                                        <div className="w-1/4 min-w-[80px] max-w-[120px] text-center text-sm font-bold text-gray-700">
                                                            {tenant.plan}

                                                        </div>

                                                        <div className="w-1/4 min-w-[80px] max-w-[120px] text-center text-sm font-bold text-gray-700">
                                                            {tenant.domain}
                                                        </div>

                                                        {/* Actions */}
                                                        <div className="w-1/5 min-w-[150px] max-w-[200px] text-right mr-8">
                                                            <div className="flex gap-2 justify-end">
                                                                <a
                                                                    href={tenant.domain ? `http://${tenant.domain}:8000` : `/app/${tenant.slug}`}
                                                                    target="_blank"
                                                                    className="rounded-[42px] bg-white p-3 text-gray-500 hover:text-green-600 hover:bg-green-50 transition-colors shadow-sm"
                                                                    title="Acceder a la tienda"
                                                                >
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" className="tabler-icon tabler-icon-world">
                                                                        <path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0"></path>
                                                                        <path d="M3.6 9h16.8"></path>
                                                                        <path d="M3.6 15h16.8"></path>
                                                                        <path d="M11.5 3a17 17 0 0 0 0 18"></path>
                                                                        <path d="M12.5 3a17 17 0 0 1 0 18"></path>
                                                                    </svg>
                                                                </a>
                                                                <Link
                                                                    href={route('superadmin.tenants.edit', tenant.id)}
                                                                    className="rounded-[42px] bg-white p-3 text-gray-500 hover:text-indigo-600 hover:bg-indigo-50 transition-colors shadow-sm"
                                                                >
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" className="tabler-icon tabler-icon-pencil">
                                                                        <path d="M4 20h4l10.5 -10.5a2.828 2.828 0 1 0 -4 -4l-10.5 10.5v4"></path>
                                                                        <path d="M13.5 6.5l4 4"></path>
                                                                    </svg>
                                                                </Link>

                                                                <div className="rounded-[42px] bg-white p-3 flex items-center justify-center text-gray-400 cursor-not-allowed shadow-sm">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" className="tabler-icon tabler-icon-calendar-event">
                                                                        <path d="M4 5m0 2a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2z"></path>
                                                                        <path d="M16 3l0 4"></path>
                                                                        <path d="M8 3l0 4"></path>
                                                                        <path d="M4 11l16 0"></path>
                                                                        <path d="M8 15h2v2h-2z"></path>
                                                                    </svg>
                                                                </div>

                                                                <button
                                                                    onClick={() => handleDelete(tenant)}
                                                                    className="rounded-[42px] bg-white p-3 text-red-500 hover:bg-red-50 transition-colors shadow-sm"
                                                                >
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" className="tabler-icon tabler-icon-trash">
                                                                        <path d="M4 7l16 0"></path>
                                                                        <path d="M10 11l0 6"></path>
                                                                        <path d="M14 11l0 6"></path>
                                                                        <path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"></path>
                                                                        <path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"></path>
                                                                    </svg>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                        {tenants.data.length === 0 && (
                                            <tr>
                                                <td className="px-6 py-12 text-center text-gray-500">
                                                    No hay tiendas que mostrar.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>

                            {/* Pagination */}
                            {tenants.last_page > 1 && (
                                <div className="mt-4 flex items-center justify-between">
                                    <div className="text-sm text-gray-700">
                                        Mostrando {(tenants.current_page - 1) * tenants.per_page + 1} a{' '}
                                        {Math.min(tenants.current_page * tenants.per_page, tenants.total)} de{' '}
                                        {tenants.total} resultados
                                    </div>
                                    <div className="flex space-x-1">
                                        {tenants.links.map((link, index) => (
                                            <Link
                                                key={index}
                                                href={link.url || '#'}
                                                className={`px-3 py-1 text-sm rounded ${
                                                    link.active
                                                        ? 'bg-indigo-600 text-white'
                                                        : link.url
                                                        ? 'bg-white text-gray-700 hover:bg-gray-50 border'
                                                        : 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                                }`}
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                            />
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </SuperAdminLayout>
    );
}
