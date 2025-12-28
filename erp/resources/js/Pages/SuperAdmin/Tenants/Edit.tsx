import SuperAdminLayout from '@/Layouts/SuperAdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface Tenant {
    id: number;
    name: string;
    slug: string;
    status: string;
    plan: string;
}

interface Props {
    tenant: Tenant;
}

export default function Edit({ tenant }: Props) {
    const { data, setData, patch, processing, errors } = useForm({
        name: tenant.name,
        slug: tenant.slug,
        status: tenant.status,
        plan: tenant.plan,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('superadmin.tenants.update', tenant.id));
    };

    return (
        <SuperAdminLayout
            header={
                <div className="flex items-center space-x-4">
                    <Link
                        href={route('superadmin.tenants.index')}
                        className="text-gray-500 hover:text-gray-700"
                    >
                        ← Volver
                    </Link>
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Editar: {tenant.name}
                    </h2>
                </div>
            }
        >
            <Head title={`Editar ${tenant.name}`} />

            <div className="py-12">
                <div className="mx-auto max-w-2xl sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <form onSubmit={submit} className="p-6 space-y-6">
                            <div>
                                <label className="block text-sm font-medium text-gray-700">
                                    Nombre de la Tienda
                                </label>
                                <input
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                                {errors.name && (
                                    <p className="mt-1 text-sm text-red-600">{errors.name}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700">
                                    Slug (URL)
                                </label>
                                <div className="mt-1 flex rounded-md shadow-sm">
                                    <span className="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">
                                        /app/
                                    </span>
                                    <input
                                        type="text"
                                        value={data.slug}
                                        onChange={(e) => setData('slug', e.target.value)}
                                        className="flex-1 block w-full rounded-none rounded-r-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                </div>
                                {errors.slug && (
                                    <p className="mt-1 text-sm text-red-600">{errors.slug}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700">
                                    Estado
                                </label>
                                <select
                                    value={data.status}
                                    onChange={(e) => setData('status', e.target.value)}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="active">Activa</option>
                                    <option value="inactive">Inactiva</option>
                                    <option value="suspended">Suspendida</option>
                                </select>
                                {errors.status && (
                                    <p className="mt-1 text-sm text-red-600">{errors.status}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700">
                                    Plan
                                </label>
                                <select
                                    value={data.plan}
                                    onChange={(e) => setData('plan', e.target.value)}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="free">Gratis</option>
                                    <option value="basic">Básico</option>
                                    <option value="pro">Pro</option>
                                    <option value="enterprise">Empresa</option>
                                </select>
                                {errors.plan && (
                                    <p className="mt-1 text-sm text-red-600">{errors.plan}</p>
                                )}
                            </div>

                            <div className="flex items-center justify-end space-x-4">
                                <Link
                                    href={route('superadmin.tenants.index')}
                                    className="text-gray-600 hover:text-gray-900"
                                >
                                    Cancelar
                                </Link>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50"
                                >
                                    {processing ? 'Guardando...' : 'Guardar Cambios'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </SuperAdminLayout>
    );
}
