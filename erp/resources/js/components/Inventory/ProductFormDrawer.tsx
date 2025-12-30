import { useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect } from 'react';
import { useTenantRoute } from '@/Hooks/useTenantRoute';
import Drawer from '@/components/ui/Drawer';
import ImageEditor from '@/components/ImageEditor';
import { ScrollArea } from '@/components/ui/scroll-area';

interface Category {
    id: number;
    name: string;
}

interface Supplier {
    id: number;
    name: string;
}

interface ProductFormDrawerProps {
    open: boolean;
    onClose: () => void;
    categories: Category[];
    suppliers: Supplier[];
    onSuccess?: () => void;
}

export default function ProductFormDrawer({
    open,
    onClose,
    categories,
    suppliers,
    onSuccess,
}: ProductFormDrawerProps) {
    const tRoute = useTenantRoute();

    const { data, setData, post, processing, errors, reset } = useForm({
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

    // Reset form when drawer opens
    useEffect(() => {
        if (open) {
            reset();
        }
    }, [open]);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(tRoute('inventory.products.store'), {
            onSuccess: () => {
                onClose();
                onSuccess?.();
            },
        });
    };

    return (
        <Drawer open={open} onClose={onClose} title="Nuevo Producto" size="lg">
            <form onSubmit={submit} className="flex flex-col h-full">
                {/* Scrollable Content */}
                <ScrollArea className="flex-1">
                    <div className="p-6">
                        <div className="space-y-6 pb-4">
                        {/* Image */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                Imagen del Producto
                            </label>
                            <ImageEditor
                                aspectRatio="1:1"
                                format="webp"
                                quality={85}
                                maxWidth={1200}
                                maxHeight={1200}
                                cropWidth={225}
                                cropHeight={225}
                                placeholder="Arrastra la imagen aquí"
                                buttonText="Seleccionar imagen"
                                onChange={(result) => setData('image', result.file)}
                            />
                            {errors.image && <p className="mt-1 text-sm text-red-600">{errors.image}</p>}
                        </div>

                        {/* Name */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Nombre del Producto *
                            </label>
                            <input
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className="w-full px-4 py-2.5 rounded-lg border border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all"
                                placeholder="Ej: Coca Cola 500ml"
                            />
                            {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                        </div>

                        {/* SKU & Barcode */}
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">SKU</label>
                                <input
                                    type="text"
                                    value={data.sku}
                                    onChange={(e) => setData('sku', e.target.value)}
                                    className="w-full px-4 py-2.5 rounded-lg border border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all"
                                    placeholder="ABC-123"
                                />
                                {errors.sku && <p className="mt-1 text-sm text-red-600">{errors.sku}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Código de Barras</label>
                                <input
                                    type="text"
                                    value={data.barcode}
                                    onChange={(e) => setData('barcode', e.target.value)}
                                    className="w-full px-4 py-2.5 rounded-lg border border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all"
                                    placeholder="7501234567890"
                                />
                                {errors.barcode && <p className="mt-1 text-sm text-red-600">{errors.barcode}</p>}
                            </div>
                        </div>

                        {/* Category & Supplier */}
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Categoría</label>
                                <select
                                    value={data.category_id}
                                    onChange={(e) => setData('category_id', e.target.value)}
                                    className="w-full px-4 py-2.5 rounded-lg border border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all bg-white"
                                >
                                    <option value="">Seleccionar...</option>
                                    {categories.map((cat) => (
                                        <option key={cat.id} value={cat.id}>{cat.name}</option>
                                    ))}
                                </select>
                                {errors.category_id && <p className="mt-1 text-sm text-red-600">{errors.category_id}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Proveedor</label>
                                <select
                                    value={data.supplier_id}
                                    onChange={(e) => setData('supplier_id', e.target.value)}
                                    className="w-full px-4 py-2.5 rounded-lg border border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all bg-white"
                                >
                                    <option value="">Seleccionar...</option>
                                    {suppliers.map((sup) => (
                                        <option key={sup.id} value={sup.id}>{sup.name}</option>
                                    ))}
                                </select>
                                {errors.supplier_id && <p className="mt-1 text-sm text-red-600">{errors.supplier_id}</p>}
                            </div>
                        </div>

                        {/* Price & Cost */}
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Precio de Venta *</label>
                                <div className="relative">
                                    <span className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">$</span>
                                    <input
                                        type="number"
                                        step="0.01"
                                        value={data.price}
                                        onChange={(e) => setData('price', e.target.value)}
                                        className="w-full pl-8 pr-4 py-2.5 rounded-lg border border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all"
                                        placeholder="0.00"
                                    />
                                </div>
                                {errors.price && <p className="mt-1 text-sm text-red-600">{errors.price}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Costo</label>
                                <div className="relative">
                                    <span className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">$</span>
                                    <input
                                        type="number"
                                        step="0.01"
                                        value={data.cost}
                                        onChange={(e) => setData('cost', e.target.value)}
                                        className="w-full pl-8 pr-4 py-2.5 rounded-lg border border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all"
                                        placeholder="0.00"
                                    />
                                </div>
                                {errors.cost && <p className="mt-1 text-sm text-red-600">{errors.cost}</p>}
                            </div>
                        </div>

                        {/* Stock & Min Stock */}
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Stock Inicial</label>
                                <input
                                    type="number"
                                    value={data.stock}
                                    onChange={(e) => setData('stock', e.target.value)}
                                    className="w-full px-4 py-2.5 rounded-lg border border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all"
                                />
                                {errors.stock && <p className="mt-1 text-sm text-red-600">{errors.stock}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Stock Mínimo</label>
                                <input
                                    type="number"
                                    value={data.min_stock}
                                    onChange={(e) => setData('min_stock', e.target.value)}
                                    className="w-full px-4 py-2.5 rounded-lg border border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all"
                                />
                                {errors.min_stock && <p className="mt-1 text-sm text-red-600">{errors.min_stock}</p>}
                            </div>
                        </div>

                        {/* Description */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
                            <textarea
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                rows={3}
                                className="w-full px-4 py-2.5 rounded-lg border border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all resize-none"
                                placeholder="Descripción del producto..."
                            />
                            {errors.description && <p className="mt-1 text-sm text-red-600">{errors.description}</p>}
                        </div>
                        </div>
                    </div>
                </ScrollArea>

                {/* Fixed Footer */}
                <div className="shrink-0 border-t border-gray-100 bg-white px-6 py-4 shadow-panel">
                    <div className="flex items-center justify-end gap-3">
                        <button
                            type="button"
                            onClick={onClose}
                            className="px-5 py-2.5  cursor-pointer text-sm font-bold text-[#334155] hover:text-gray-800 hover:bg-gray-100 rounded-lg transition-colors"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            disabled={processing}
                            className="px-5 py-2.5 cursor-pointer text-sm font-bold text-white bg-primary hover:bg-primary rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                        >
                            {processing ? (
                                <>
                                    <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
                                    Guardando...
                                </>
                            ) : (
                                'Guardar Producto'
                            )}
                        </button>
                    </div>
                </div>
            </form>
        </Drawer>
    );
}
