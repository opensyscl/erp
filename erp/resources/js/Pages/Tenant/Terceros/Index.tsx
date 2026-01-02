import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, Link } from '@inertiajs/react';
import { useTenantRoute } from '@/Hooks/useTenantRoute';
import { useState, useCallback } from 'react';
import { toast } from 'sonner';
import { Users, Truck, Plus, X, Edit2 } from 'lucide-react';

interface Entity {
    id: number;
    name: string;
    rut: string | null;
    address: string | null;
    city: string | null;
    email: string | null;
    phone: string | null;
    image: string | null;
    created_at: string;
}

interface Props {
    clients: Entity[];
    suppliers: Entity[];
    activeTab: 'clients' | 'suppliers';
}

const emptyForm = {
    id: 0,
    name: '',
    rut: '',
    address: '',
    city: '',
    email: '',
    phone: '',
    image: '',
};

export default function Index({ clients, suppliers, activeTab }: Props) {
    const tRoute = useTenantRoute();
    const [tab, setTab] = useState<'clients' | 'suppliers'>(activeTab);
    const [form, setForm] = useState(emptyForm);
    const [isEditing, setIsEditing] = useState(false);
    const [saving, setSaving] = useState(false);

    const resetForm = () => {
        setForm(emptyForm);
        setIsEditing(false);
    };

    const selectEntity = (entity: Entity) => {
        setForm({
            id: entity.id,
            name: entity.name,
            rut: entity.rut || '',
            address: entity.address || '',
            city: entity.city || '',
            email: entity.email || '',
            phone: entity.phone || '',
            image: entity.image || '',
        });
        setIsEditing(true);
    };

    const handleSubmit = useCallback((e: React.FormEvent) => {
        e.preventDefault();
        if (!form.name.trim()) {
            toast.error('El nombre es requerido');
            return;
        }
        setSaving(true);

        const isClient = tab === 'clients';
        const routeName = isEditing
            ? (isClient ? 'terceros.clients.update' : 'terceros.suppliers.update')
            : (isClient ? 'terceros.clients.store' : 'terceros.suppliers.store');

        const method = isEditing ? 'put' : 'post';
        const url = isEditing
            ? tRoute(routeName, { id: form.id })
            : tRoute(routeName);

        router[method](url, {
            name: form.name,
            rut: form.rut,
            address: form.address,
            city: form.city,
            email: form.email,
            phone: form.phone,
            image: form.image,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(isEditing ? 'Actualizado exitosamente' : 'Registrado exitosamente');
                resetForm();
                setSaving(false);
            },
            onError: () => {
                toast.error('Error al guardar');
                setSaving(false);
            },
        });
    }, [form, tab, isEditing, tRoute]);

    const entities = tab === 'clients' ? clients : suppliers;
    const entityLabel = tab === 'clients' ? 'Cliente' : 'Proveedor';
    const placeholderColor = tab === 'clients' ? '4F46E5' : 'EF4444';
    const placeholderText = tab === 'clients' ? 'CL' : 'PR';

    return (
        <AuthenticatedLayout>
            <Head title="Gestión de Terceros" />

            <div className="p-6 max-w-7xl mx-auto">
                <h1 className="text-2xl font-bold text-gray-900 mb-6">Gestión de Terceros</h1>

                {/* Tabs */}
                <div className="flex gap-2 mb-6">
                    <button
                        onClick={() => { setTab('suppliers'); resetForm(); }}
                        className={`px-4 py-2 rounded-lg font-medium flex items-center gap-2 ${
                            tab === 'suppliers'
                                ? 'bg-red-500 text-white'
                                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                        }`}
                    >
                        <Truck className="w-4 h-4" />
                        Proveedores ({suppliers.length})
                    </button>
                    <button
                        onClick={() => { setTab('clients'); resetForm(); }}
                        className={`px-4 py-2 rounded-lg font-medium flex items-center gap-2 ${
                            tab === 'clients'
                                ? 'bg-indigo-500 text-white'
                                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                        }`}
                    >
                        <Users className="w-4 h-4" />
                        Clientes ({clients.length})
                    </button>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Form Panel */}
                    <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h2 className="font-semibold text-lg mb-4">
                            {isEditing ? `Editar ${entityLabel}` : `Registrar ${entityLabel}`}
                        </h2>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Nombre / Razón Social *</label>
                                <input
                                    type="text"
                                    value={form.name}
                                    onChange={(e) => setForm({ ...form, name: e.target.value })}
                                    className="w-full px-3 py-2 border rounded-lg"
                                    required
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">RUT</label>
                                <input
                                    type="text"
                                    value={form.rut}
                                    onChange={(e) => setForm({ ...form, rut: e.target.value })}
                                    className="w-full px-3 py-2 border rounded-lg"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Dirección</label>
                                <input
                                    type="text"
                                    value={form.address}
                                    onChange={(e) => setForm({ ...form, address: e.target.value })}
                                    className="w-full px-3 py-2 border rounded-lg"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Comuna</label>
                                <input
                                    type="text"
                                    value={form.city}
                                    onChange={(e) => setForm({ ...form, city: e.target.value })}
                                    className="w-full px-3 py-2 border rounded-lg"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Correo</label>
                                <input
                                    type="email"
                                    value={form.email}
                                    onChange={(e) => setForm({ ...form, email: e.target.value })}
                                    className="w-full px-3 py-2 border rounded-lg"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                                <input
                                    type="tel"
                                    value={form.phone}
                                    onChange={(e) => setForm({ ...form, phone: e.target.value })}
                                    className="w-full px-3 py-2 border rounded-lg"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">URL de Imagen/Logo</label>
                                <input
                                    type="url"
                                    value={form.image}
                                    onChange={(e) => setForm({ ...form, image: e.target.value })}
                                    className="w-full px-3 py-2 border rounded-lg"
                                    placeholder="https://..."
                                />
                            </div>

                            <div className="flex gap-2 pt-2">
                                <button
                                    type="submit"
                                    disabled={saving}
                                    className={`flex-1 py-2 rounded-lg font-medium text-white flex items-center justify-center gap-2 ${
                                        tab === 'clients' ? 'bg-indigo-500 hover:bg-indigo-600' : 'bg-red-500 hover:bg-red-600'
                                    } disabled:opacity-50`}
                                >
                                    {isEditing ? <Edit2 className="w-4 h-4" /> : <Plus className="w-4 h-4" />}
                                    {saving ? 'Guardando...' : (isEditing ? `Actualizar ${entityLabel}` : `Añadir ${entityLabel}`)}
                                </button>
                                {isEditing && (
                                    <button
                                        type="button"
                                        onClick={resetForm}
                                        className="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50"
                                    >
                                        Cancelar
                                    </button>
                                )}
                            </div>
                        </form>
                    </div>

                    {/* Grid Panel */}
                    <div className="lg:col-span-2">
                        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                            <h2 className="font-semibold text-lg mb-4">
                                {tab === 'clients' ? 'Clientes' : 'Proveedores'} Registrados ({entities.length})
                            </h2>

                            {entities.length === 0 ? (
                                <p className="text-gray-400 text-center py-8">No hay {tab === 'clients' ? 'clientes' : 'proveedores'} registrados.</p>
                            ) : (
                                <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                                    {entities.map((entity) => (
                                        <button
                                            key={entity.id}
                                            onClick={() => selectEntity(entity)}
                                            className={`p-4 border rounded-xl text-left hover:shadow-md transition-shadow flex items-start gap-3 ${
                                                form.id === entity.id ? 'ring-2 ring-primary border-primary' : 'border-gray-200'
                                            }`}
                                        >
                                            <img
                                                src={entity.image || `https://placehold.co/80x80/${placeholderColor}/FFFFFF?text=${placeholderText}`}
                                                alt={entity.name}
                                                className="w-16 h-16 rounded-lg object-cover flex-shrink-0"
                                                onError={(e) => {
                                                    (e.target as HTMLImageElement).src = `https://placehold.co/80x80/${placeholderColor}/FFFFFF?text=${placeholderText}`;
                                                }}
                                            />
                                            <div className="flex-1 min-w-0">
                                                <p className="font-medium text-gray-900 truncate">{entity.name}</p>
                                                <p className="text-sm text-gray-500">RUT: {entity.rut || 'N/A'}</p>
                                                {entity.phone && (
                                                    <p className="text-xs text-gray-400 mt-1">{entity.phone}</p>
                                                )}
                                            </div>
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
