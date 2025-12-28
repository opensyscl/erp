import SuperAdminLayout from '@/Layouts/SuperAdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect } from 'react';

export default function Create() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        slug: '',
        plan: 'free',
        admin_name: '',
        admin_email: '',
        admin_password: '',
    });

    // Auto-generate slug from name
    useEffect(() => {
        const slug = data.name
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '');
        setData('slug', slug);
    }, [data.name]);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('superadmin.tenants.store'));
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
                        Nueva Tienda
                    </h2>
                </div>
            }
        >
            <Head title="Nueva Tienda" />

            <div className="py-12">
                <div className="mx-auto max-w-2xl sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <form onSubmit={submit} className="p-6 space-y-6">
                            {/* Tenant Info */}
                            <div>
                                <h3 className="text-lg font-medium text-gray-900 mb-4">
                                    Información de la Tienda
                                </h3>

                                <div className="space-y-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">
                                            Nombre de la Tienda
                                        </label>
                                        <input
                                            type="text"
                                            value={data.name}
                                            onChange={(e) => setData('name', e.target.value)}
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            placeholder="Mi Tienda"
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
                                                placeholder="mi-tienda"
                                            />
                                        </div>
                                        {errors.slug && (
                                            <p className="mt-1 text-sm text-red-600">{errors.slug}</p>
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
                                </div>
                            </div>

                            <hr />

                            {/* Admin Info */}
                            <div>
                                <h3 className="text-lg font-medium text-gray-900 mb-4">
                                    Administrador de la Tienda
                                </h3>

                                <div className="space-y-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">
                                            Nombre del Admin
                                        </label>
                                        <input
                                            type="text"
                                            value={data.admin_name}
                                            onChange={(e) => setData('admin_name', e.target.value)}
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            placeholder="Juan Pérez"
                                        />
                                        {errors.admin_name && (
                                            <p className="mt-1 text-sm text-red-600">{errors.admin_name}</p>
                                        )}
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">
                                            Email del Admin
                                        </label>
                                        <input
                                            type="email"
                                            value={data.admin_email}
                                            onChange={(e) => setData('admin_email', e.target.value)}
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            placeholder="admin@mitienda.com"
                                        />
                                        {errors.admin_email && (
                                            <p className="mt-1 text-sm text-red-600">{errors.admin_email}</p>
                                        )}
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">
                                            Contraseña
                                        </label>
                                        <input
                                            type="password"
                                            value={data.admin_password}
                                            onChange={(e) => setData('admin_password', e.target.value)}
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            placeholder="••••••••"
                                        />
                                        {errors.admin_password && (
                                            <p className="mt-1 text-sm text-red-600">{errors.admin_password}</p>
                                        )}
                                    </div>
                                </div>
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
                                    {processing ? 'Creando...' : 'Crear Tienda'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </SuperAdminLayout>
    );
}
