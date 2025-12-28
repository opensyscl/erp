import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import { useTenantRoute } from '@/Hooks/useTenantRoute';

interface Category {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    is_active: boolean;
    products_count: number;
}

interface Props {
    categories: Category[];
}

export default function Index({ categories }: Props) {
    const tRoute = useTenantRoute();
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editName, setEditName] = useState('');
    const [editDescription, setEditDescription] = useState('');

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        description: '',
    });

    const handleCreate = (e: React.FormEvent) => {
        e.preventDefault();
        post(tRoute('inventory.categories.store'), {
            onSuccess: () => reset(),
            preserveScroll: true,
        });
    };

    const handleUpdate = (id: number) => {
        router.patch(tRoute('inventory.categories.update', { category: id }), {
            id: id,
            name: editName,
            description: editDescription,
        }, {
            onSuccess: () => {
                setEditingId(null);
                setEditName('');
                setEditDescription('');
            },
            preserveScroll: true,
        });
    };

    const handleDelete = (category: Category) => {
        if (category.products_count > 0) {
            alert(`No puedes eliminar "${category.name}" porque tiene ${category.products_count} productos.`);
            return;
        }
        if (confirm(`¿Eliminar "${category.name}"?`)) {
            router.delete(tRoute('inventory.categories.destroy', { category: category.id }), {
                preserveScroll: true,
            });
        }
    };

    const startEdit = (category: Category) => {
        setEditingId(category.id);
        setEditName(category.name);
        setEditDescription(category.description || '');
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center space-x-4">
                    <Link
                        href={tRoute('inventory.index')}
                        className="text-gray-500 hover:text-gray-700"
                    >
                        ← Inventario
                    </Link>
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        📁 Categorías
                    </h2>
                </div>
            }
        >
            <Head title="Categorías" />

            <div className="py-6">
                <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                    {/* Create Form */}
                    <div className="bg-white rounded-lg shadow p-6 mb-6">
                        <h3 className="text-lg font-medium text-gray-900 mb-4">Nueva Categoría</h3>
                        <form onSubmit={handleCreate} className="flex gap-4">
                            <input
                                type="text"
                                placeholder="Nombre de la categoría"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className="flex-1 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            />
                            <input
                                type="text"
                                placeholder="Descripción (opcional)"
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                className="flex-1 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            />
                            <button
                                type="submit"
                                disabled={processing || !data.name}
                                className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
                            >
                                + Agregar
                            </button>
                        </form>
                        {errors.name && <p className="mt-2 text-sm text-red-600">{errors.name}</p>}
                    </div>

                    {/* Categories List */}
                    <div className="bg-white rounded-lg shadow overflow-hidden">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                        Categoría
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                        Descripción
                                    </th>
                                    <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                                        Productos
                                    </th>
                                    <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {categories.map((category) => (
                                    <tr key={category.id}>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            {editingId === category.id ? (
                                                <input
                                                    type="text"
                                                    value={editName}
                                                    onChange={(e) => setEditName(e.target.value)}
                                                    className="rounded border-gray-300 text-sm"
                                                />
                                            ) : (
                                                <span className="font-medium text-gray-900">{category.name}</span>
                                            )}
                                        </td>
                                        <td className="px-6 py-4">
                                            {editingId === category.id ? (
                                                <input
                                                    type="text"
                                                    value={editDescription}
                                                    onChange={(e) => setEditDescription(e.target.value)}
                                                    className="rounded border-gray-300 text-sm w-full"
                                                />
                                            ) : (
                                                <span className="text-gray-500 text-sm">{category.description || '-'}</span>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 text-center">
                                            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                {category.products_count}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-right space-x-2">
                                            {editingId === category.id ? (
                                                <>
                                                    <button
                                                        onClick={() => handleUpdate(category.id)}
                                                        className="text-green-600 hover:text-green-800 text-sm"
                                                    >
                                                        ✓ Guardar
                                                    </button>
                                                    <button
                                                        onClick={() => { setEditingId(null); setEditName(''); setEditDescription(''); }}
                                                        className="text-gray-600 hover:text-gray-800 text-sm"
                                                    >
                                                        ✕ Cancelar
                                                    </button>
                                                </>
                                            ) : (
                                                <>
                                                    <button
                                                        onClick={() => startEdit(category)}
                                                        className="text-blue-600 hover:text-blue-800 text-sm"
                                                    >
                                                        ✏️ Editar
                                                    </button>
                                                    <button
                                                        onClick={() => handleDelete(category)}
                                                        className="text-red-600 hover:text-red-800 text-sm"
                                                    >
                                                        🗑️ Eliminar
                                                    </button>
                                                </>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                                {categories.length === 0 && (
                                    <tr>
                                        <td colSpan={4} className="px-6 py-12 text-center text-gray-500">
                                            No hay categorías. Crea una arriba.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
