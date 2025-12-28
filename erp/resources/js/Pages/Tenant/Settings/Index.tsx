import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';

import { FormEventHandler } from 'react';
import { useTenantRoute } from '@/Hooks/useTenantRoute';

interface Props {
    settings: {
        brand_color: string;
        logo?: string;
    };
}

export default function Index({ settings }: Props) {
    const tRoute = useTenantRoute();

    // Obtenemos el auth user para mostrar el nombre si es necesario
    const user = usePage().props.auth.user;

    const { data, setData, post, processing, errors, recentlySuccessful } = useForm({
        brand_color: settings.brand_color,
        logo: null as File | null,
        _method: 'patch',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(tRoute('settings.update'), {
            preserveScroll: true,
        });
    };



    return (
        <AuthenticatedLayout
            settings={settings}
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    ⚙️ Configuración de la Tienda
                </h2>
            }
        >
            <Head title="Configuración" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <div className="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                        <section className="max-w-xl">
                            <header>
                                <h2 className="text-lg font-medium text-gray-900">
                                    Apariencia
                                </h2>
                                <p className="mt-1 text-sm text-gray-600">
                                    Personaliza el logo y color principal de tu tienda.
                                </p>
                            </header>

                            <form onSubmit={submit} className="mt-6 space-y-6">
                                {/* Logo Input */}
                                <div>
                                    <label htmlFor="logo" className="block text-sm font-medium text-gray-700">
                                        Logo de la Tienda
                                    </label>
                                    <div className="mt-2">
                                        {settings.logo && (
                                            <div className="mb-4">
                                                <img src={settings.logo} alt="Current Logo" className="h-16 w-auto object-contain rounded border" />
                                            </div>
                                        )}
                                        <input
                                            id="logo"
                                            type="file"
                                            accept="image/*"
                                            className="block w-full text-sm text-gray-500
                                                file:mr-4 file:py-2 file:px-4
                                                file:rounded-full file:border-0
                                                file:text-sm file:font-semibold
                                                file:bg-blue-50 file:text-blue-700
                                                hover:file:bg-blue-100"
                                            onChange={(e) => setData('logo', e.target.files ? e.target.files[0] : null)}
                                        />
                                        <p className="mt-1 text-xs text-gray-500">PNG, JPG, hasta 2MB.</p>
                                    </div>
                                    {errors.logo && (
                                        <p className="mt-2 text-sm text-red-600">{errors.logo}</p>
                                    )}
                                </div>

                                {/* Color Input */}
                                <div>
                                    <label htmlFor="brand_color" className="block text-sm font-medium text-gray-700">
                                        Color de Marca
                                    </label>
                                    <div className="mt-2 flex items-center gap-4">
                                        <input
                                            id="brand_color"
                                            type="color"
                                            className="h-10 w-20 cursor-pointer rounded border border-gray-300 p-1"
                                            value={data.brand_color}
                                            onChange={(e) => setData('brand_color', e.target.value)}
                                        />
                                        <span className="text-sm font-mono text-gray-600 uppercase">
                                            {data.brand_color}
                                        </span>
                                    </div>
                                    {errors.brand_color && (
                                        <p className="mt-2 text-sm text-red-600">{errors.brand_color}</p>
                                    )}
                                </div>

                                <div className="flex items-center gap-4">
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 active:bg-gray-900 disabled:opacity-50"
                                    >
                                        Guardar cambios
                                    </button>

                                    {recentlySuccessful && (
                                        <p className="text-sm text-green-600 animate-fade-in-out">
                                            Guardado.
                                        </p>
                                    )}
                                </div>
                            </form>
                        </section>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
