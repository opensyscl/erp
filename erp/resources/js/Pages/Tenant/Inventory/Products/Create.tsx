import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { useTenantRoute } from '@/Hooks/useTenantRoute';
import ImageEditor from '@/components/ImageEditor';

interface Category {
    id: number;
    name: string;
}

interface Supplier {
    id: number;
    name: string;
}

interface Props {
    categories: Category[];
    suppliers: Supplier[];
}

export default function Create({ categories, suppliers }: Props) {
    const tRoute = useTenantRoute();

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        sku: '',
        barcode: '',
        description: '',
        category_id: '',
        supplier_id: '',
        price: '',
        cost: '',
        stock: '0',
        min_stock: '5',
        image: null as File | null,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(tRoute('inventory.products.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center space-x-4">
                    <Link
                        href={tRoute('inventory.index')}
                        className="text-gray-500 hover:text-gray-700"
                    >
                        ← Volver
                    </Link>
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Nuevo Producto
                    </h2>
                </div>
            }
        >
            <Head title="Nuevo Producto" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <form onSubmit={submit} className="p-6 space-y-6">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                {/* Name */}
                                <div className="md:col-span-2">
                                    <label className="block text-sm font-medium text-gray-700">
                                        Nombre del Producto *
                                    </label>
                                    <input
                                        type="text"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        placeholder="Ej: Coca Cola 500ml"
                                    />
                                    {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                                </div>

                                {/* SKU */}
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">SKU</label>
                                    <input
                                        type="text"
                                        value={data.sku}
                                        onChange={(e) => setData('sku', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        placeholder="Código único"
                                    />
                                    {errors.sku && <p className="mt-1 text-sm text-red-600">{errors.sku}</p>}
                                </div>

                                {/* Barcode */}
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Código de Barras</label>
                                    <input
                                        type="text"
                                        value={data.barcode}
                                        onChange={(e) => setData('barcode', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        placeholder="EAN/UPC"
                                    />
                                    {errors.barcode && <p className="mt-1 text-sm text-red-600">{errors.barcode}</p>}
                                </div>

                                {/* Category */}
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Categoría</label>
                                    <select
                                        value={data.category_id}
                                        onChange={(e) => setData('category_id', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    >
                                        <option value="">Sin categoría</option>
                                        {categories.map((cat) => (
                                            <option key={cat.id} value={cat.id}>{cat.name}</option>
                                        ))}
                                    </select>
                                    {errors.category_id && <p className="mt-1 text-sm text-red-600">{errors.category_id}</p>}
                                </div>

                                {/* Supplier */}
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Proveedor</label>
                                    <select
                                        value={data.supplier_id}
                                        onChange={(e) => setData('supplier_id', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    >
                                        <option value="">Sin proveedor</option>
                                        {suppliers.map((sup) => (
                                            <option key={sup.id} value={sup.id}>{sup.name}</option>
                                        ))}
                                    </select>
                                    {errors.supplier_id && <p className="mt-1 text-sm text-red-600">{errors.supplier_id}</p>}
                                </div>

                                {/* Price */}
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Precio de Venta *</label>
                                    <div className="mt-1 relative">
                                        <span className="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">$</span>
                                        <input
                                            type="number"
                                            value={data.price}
                                            onChange={(e) => setData('price', e.target.value)}
                                            className="block w-full pl-8 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            placeholder="0"
                                            min="0"
                                            step="1"
                                        />
                                    </div>
                                    {errors.price && <p className="mt-1 text-sm text-red-600">{errors.price}</p>}
                                </div>

                                {/* Cost */}
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Costo Bruto</label>
                                    <div className="mt-1 relative">
                                        <span className="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500">$</span>
                                        <input
                                            type="number"
                                            value={data.cost}
                                            onChange={(e) => setData('cost', e.target.value)}
                                            className="block w-full pl-8 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            placeholder="0"
                                            min="0"
                                            step="1"
                                        />
                                    </div>
                                    {errors.cost && <p className="mt-1 text-sm text-red-600">{errors.cost}</p>}
                                </div>

                                {/* Stock */}
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Stock Actual</label>
                                    <input
                                        type="number"
                                        value={data.stock}
                                        onChange={(e) => setData('stock', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        min="0"
                                    />
                                    {errors.stock && <p className="mt-1 text-sm text-red-600">{errors.stock}</p>}
                                </div>

                                {/* Min Stock */}
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Stock Mínimo (alerta)</label>
                                    <input
                                        type="number"
                                        value={data.min_stock}
                                        onChange={(e) => setData('min_stock', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        min="0"
                                    />
                                    {errors.min_stock && <p className="mt-1 text-sm text-red-600">{errors.min_stock}</p>}
                                </div>

                                {/* Description */}
                                <div className="md:col-span-2">
                                    <label className="block text-sm font-medium text-gray-700">Descripción</label>
                                    <textarea
                                        value={data.description}
                                        onChange={(e) => setData('description', e.target.value)}
                                        rows={3}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    />
                                    {errors.description && <p className="mt-1 text-sm text-red-600">{errors.description}</p>}
                                </div>

                                {/* Image */}
                                <div className="md:col-span-2">
                                    <label className="block text-sm font-medium text-gray-700 mb-2">Imagen del Producto</label>
                                    <ImageEditor
                                        aspectRatio="1:1"
                                        format="webp"
                                        quality={85}
                                        maxWidth={1200}
                                        maxHeight={1200}
                                        cropWidth={225}
                                        cropHeight={225}
                                        placeholder="Arrastra la imagen del producto aquí"
                                        buttonText="Seleccionar imagen"
                                        onChange={(result) => setData('image', result.file)}
                                    />
                                    {errors.image && <p className="mt-1 text-sm text-red-600">{errors.image}</p>}
                                </div>
                            </div>

                            <div className="flex items-center justify-end space-x-4 pt-4 border-t">
                                <Link
                                    href={tRoute('inventory.index')}
                                    className="text-gray-600 hover:text-gray-900"
                                >
                                    Cancelar
                                </Link>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition disabled:opacity-50"
                                >
                                    {processing ? 'Guardando...' : 'Crear Producto'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
