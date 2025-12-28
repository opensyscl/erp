import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import { useTenantRoute } from '@/Hooks/useTenantRoute';

interface Supplier {
    id: number;
    name: string;
    contact_name: string | null;
    email: string | null;
    phone: string | null;
    rut: string | null;
    is_active: boolean;
    products_count: number;
}

interface Props {
    suppliers: Supplier[];
}

export default function Index({ suppliers }: Props) {
    const tRoute = useTenantRoute();
    const [showForm, setShowForm] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);

    const { data, setData, post, patch, processing, errors, reset } = useForm({
        name: '',
        contact_name: '',
        email: '',
        phone: '',
        rut: '',
    });

    const handleCreate = (e: React.FormEvent) => {
        e.preventDefault();
        post(tRoute('inventory.suppliers.store'), {
            onSuccess: () => {
                reset();
                setShowForm(false);
            },
        });
    };

    const handleUpdate = (id: number) => {
        patch(tRoute('inventory.suppliers.update', { supplier: id }), {
            onSuccess: () => {
                setEditingId(null);
                reset();
            },
        });
    };

    const handleDelete = (supplier: Supplier) => {
        if (supplier.products_count > 0) {
            alert(`No puedes eliminar "${supplier.name}" porque tiene ${supplier.products_count} productos.`);
            return;
        }
        if (confirm(`¿Eliminar "${supplier.name}"?`)) {
            router.delete(tRoute('inventory.suppliers.destroy', { supplier: supplier.id }));
        }
    };

    const startEdit = (supplier: Supplier) => {
        setEditingId(supplier.id);
        setData({
            name: supplier.name,
            contact_name: supplier.contact_name || '',
            email: supplier.email || '',
            phone: supplier.phone || '',
            rut: supplier.rut || '',
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-4">
                        <Link
                            href={tRoute('inventory.index')}
                            className="text-gray-500 hover:text-gray-700"
                        >
                            ← Inventario
                        </Link>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            🏢 Proveedores
                        </h2>
                    </div>
                    <button
                        onClick={() => setShowForm(!showForm)}
                        className="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700"
                    >
                        + Nuevo Proveedor
                    </button>
                </div>
            }
        >
            <Head title="Proveedores" />

            <div className="py-6">
                <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                    {/* Create Form */}
                    {showForm && (
                        <div className="bg-white rounded-lg shadow p-6 mb-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Nuevo Proveedor</h3>
                            <form onSubmit={handleCreate} className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Nombre *</label>
                                    <input
                                        type="text"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        className="mt-1 w-full rounded-lg border-gray-300"
                                        placeholder="Distribuidora XYZ"
                                    />
                                    {errors.name && <p className="text-sm text-red-600">{errors.name}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Contacto</label>
                                    <input
                                        type="text"
                                        value={data.contact_name}
                                        onChange={(e) => setData('contact_name', e.target.value)}
                                        className="mt-1 w-full rounded-lg border-gray-300"
                                        placeholder="Juan Pérez"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Email</label>
                                    <input
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        className="mt-1 w-full rounded-lg border-gray-300"
                                        placeholder="contacto@proveedor.cl"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Teléfono</label>
                                    <input
                                        type="text"
                                        value={data.phone}
                                        onChange={(e) => setData('phone', e.target.value)}
                                        className="mt-1 w-full rounded-lg border-gray-300"
                                        placeholder="+56 9 1234 5678"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">RUT</label>
                                    <input
                                        type="text"
                                        value={data.rut}
                                        onChange={(e) => setData('rut', e.target.value)}
                                        className="mt-1 w-full rounded-lg border-gray-300"
                                        placeholder="76.123.456-7"
                                    />
                                </div>
                                <div className="flex items-end gap-2">
                                    <button
                                        type="submit"
                                        disabled={processing || !data.name}
                                        className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
                                    >
                                        Crear Proveedor
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => { setShowForm(false); reset(); }}
                                        className="px-4 py-2 text-gray-600 hover:text-gray-800"
                                    >
                                        Cancelar
                                    </button>
                                </div>
                            </form>
                        </div>
                    )}

                    {/* Suppliers Grid */}
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        {suppliers.map((supplier) => (
                            <div key={supplier.id} className="bg-white rounded-lg shadow p-4">
                                {editingId === supplier.id ? (
                                    <div className="space-y-2">
                                        <input
                                            type="text"
                                            value={data.name}
                                            onChange={(e) => setData('name', e.target.value)}
                                            className="w-full rounded border-gray-300 text-sm"
                                            placeholder="Nombre"
                                        />
                                        <input
                                            type="text"
                                            value={data.contact_name}
                                            onChange={(e) => setData('contact_name', e.target.value)}
                                            className="w-full rounded border-gray-300 text-sm"
                                            placeholder="Contacto"
                                        />
                                        <input
                                            type="email"
                                            value={data.email}
                                            onChange={(e) => setData('email', e.target.value)}
                                            className="w-full rounded border-gray-300 text-sm"
                                            placeholder="Email"
                                        />
                                        <input
                                            type="text"
                                            value={data.phone}
                                            onChange={(e) => setData('phone', e.target.value)}
                                            className="w-full rounded border-gray-300 text-sm"
                                            placeholder="Teléfono"
                                        />
                                        <div className="flex gap-2 pt-2">
                                            <button
                                                onClick={() => handleUpdate(supplier.id)}
                                                className="px-3 py-1 bg-green-600 text-white text-sm rounded"
                                            >
                                                Guardar
                                            </button>
                                            <button
                                                onClick={() => { setEditingId(null); reset(); }}
                                                className="px-3 py-1 text-gray-600 text-sm"
                                            >
                                                Cancelar
                                            </button>
                                        </div>
                                    </div>
                                ) : (
                                    <>
                                        <div className="flex items-start justify-between">
                                            <div>
                                                <h3 className="font-semibold text-gray-900">{supplier.name}</h3>
                                                {supplier.contact_name && (
                                                    <p className="text-sm text-gray-500">👤 {supplier.contact_name}</p>
                                                )}
                                            </div>
                                            <span className="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded">
                                                {supplier.products_count} productos
                                            </span>
                                        </div>
                                        <div className="mt-3 space-y-1 text-sm text-gray-600">
                                            {supplier.email && <p>📧 {supplier.email}</p>}
                                            {supplier.phone && <p>📞 {supplier.phone}</p>}
                                            {supplier.rut && <p>🏢 {supplier.rut}</p>}
                                        </div>
                                        <div className="mt-4 flex gap-2 border-t pt-3">
                                            <button
                                                onClick={() => startEdit(supplier)}
                                                className="text-blue-600 hover:text-blue-800 text-sm"
                                            >
                                                ✏️ Editar
                                            </button>
                                            <button
                                                onClick={() => handleDelete(supplier)}
                                                className="text-red-600 hover:text-red-800 text-sm"
                                            >
                                                🗑️ Eliminar
                                            </button>
                                        </div>
                                    </>
                                )}
                            </div>
                        ))}

                        {suppliers.length === 0 && (
                            <div className="col-span-full bg-white rounded-lg shadow p-12 text-center">
                                <div className="text-5xl mb-4">🏢</div>
                                <h3 className="text-lg font-medium text-gray-900 mb-2">Sin proveedores</h3>
                                <p className="text-gray-500">Agrega proveedores para asociarlos a tus productos.</p>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
