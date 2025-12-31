import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { useTenantRoute } from '@/Hooks/useTenantRoute';
import { useState } from 'react';
import {
    FileText,
    Plus,
    Trash2,
    Search,
    Package,
    Building2,
    Calendar,
    Save,
    ArrowLeft
} from 'lucide-react';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

interface Supplier {
    id: number;
    name: string;
}

interface Product {
    id: number;
    name: string;
    sku: string | null;
    cost: number;
    price: number;
    stock: number;
}

interface InvoiceItem {
    product_id: number;
    product_name: string;
    quantity: number;
    new_cost: number;
    margin_percentage: number | null;
    calculated_sale_price: number | null;
    update_product_cost: boolean;
    update_product_price: boolean;
}

interface Props {
    suppliers: Supplier[];
    products: Product[];
}

export default function Create({ suppliers, products }: Props) {
    const tRoute = useTenantRoute();
    const [items, setItems] = useState<InvoiceItem[]>([]);
    const [productSearch, setProductSearch] = useState('');
    const [showProductSearch, setShowProductSearch] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        supplier_id: '',
        invoice_number: '',
        invoice_date: new Date().toISOString().split('T')[0],
        received_date: new Date().toISOString().split('T')[0],
        notes: '',
        items: [] as InvoiceItem[],
    });

    const formatPrice = (price: number) => {
        return new Intl.NumberFormat('es-CL', {
            style: 'currency',
            currency: 'CLP',
        }).format(price);
    };

    const calculateSubtotal = () => {
        return items.reduce((sum, item) => sum + (item.quantity * item.new_cost), 0);
    };

    const calculateTotal = () => {
        const subtotal = calculateSubtotal();
        return subtotal * 1.19; // Con IVA 19%
    };

    const addProduct = (product: Product) => {
        // Check if product already exists
        if (items.find(item => item.product_id === product.id)) {
            setProductSearch('');
            setShowProductSearch(false);
            return;
        }

        const newItem: InvoiceItem = {
            product_id: product.id,
            product_name: product.name,
            quantity: 1,
            new_cost: product.cost,
            margin_percentage: null,
            calculated_sale_price: null,
            update_product_cost: true,
            update_product_price: false,
        };

        setItems([...items, newItem]);
        setProductSearch('');
        setShowProductSearch(false);
    };

    const updateItem = (index: number, field: keyof InvoiceItem, value: any) => {
        const newItems = [...items];
        newItems[index] = { ...newItems[index], [field]: value };

        // Calculate sale price if margin is set
        if (field === 'margin_percentage' && value !== null && value !== '') {
            const margin = parseFloat(value);
            const cost = newItems[index].new_cost;
            newItems[index].calculated_sale_price = Math.round(cost * (1 + margin / 100));
        }

        setItems(newItems);
    };

    const removeItem = (index: number) => {
        setItems(items.filter((_, i) => i !== index));
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (items.length === 0) {
            alert('Debes agregar al menos un producto.');
            return;
        }

        // Update form data with items
        const formData = {
            ...data,
            items: items,
        };

        post(tRoute('purchases.store'), {
            data: formData,
        });
    };

    const filteredProducts = products.filter(p =>
        p.name.toLowerCase().includes(productSearch.toLowerCase()) ||
        (p.sku && p.sku.toLowerCase().includes(productSearch.toLowerCase()))
    ).slice(0, 10);

    return (
        <AuthenticatedLayout>
            <Head title="Nueva Factura de Compra" />

            <div className="py-6 px-4 sm:px-6 lg:px-8 max-w-[1200px] mx-auto">
                {/* Header */}
                <div className="flex items-center gap-4 mb-8">
                    <Link
                        href={tRoute('purchases.index')}
                        className="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
                    >
                        <ArrowLeft className="w-5 h-5" />
                    </Link>
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Nueva Factura de Compra</h1>
                        <p className="text-gray-500 mt-1">Registra la mercadería recibida de un proveedor</p>
                    </div>
                </div>

                <form onSubmit={handleSubmit}>
                    {/* Invoice Info */}
                    <div className="bg-white rounded-2xl border border-gray-100 p-6 mb-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <FileText className="w-5 h-5 text-primary" />
                            Información de la Factura
                        </h2>

                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            {/* Supplier */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Proveedor *
                                </label>
                                <select
                                    value={data.supplier_id}
                                    onChange={(e) => setData('supplier_id', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                                    required
                                >
                                    <option value="">Seleccionar proveedor</option>
                                    {suppliers.map(sup => (
                                        <option key={sup.id} value={sup.id}>{sup.name}</option>
                                    ))}
                                </select>
                                {errors.supplier_id && <p className="text-xs text-danger mt-1">{errors.supplier_id}</p>}
                            </div>

                            {/* Invoice Number */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    N° Factura *
                                </label>
                                <input
                                    type="text"
                                    value={data.invoice_number}
                                    onChange={(e) => setData('invoice_number', e.target.value)}
                                    placeholder="Ej: 12345"
                                    className="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                                    required
                                />
                                {errors.invoice_number && <p className="text-xs text-danger mt-1">{errors.invoice_number}</p>}
                            </div>

                            {/* Invoice Date */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Fecha Factura *
                                </label>
                                <input
                                    type="date"
                                    value={data.invoice_date}
                                    onChange={(e) => setData('invoice_date', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                                    required
                                />
                            </div>

                            {/* Received Date */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Fecha Recepción
                                </label>
                                <input
                                    type="date"
                                    value={data.received_date}
                                    onChange={(e) => setData('received_date', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                                />
                            </div>
                        </div>
                    </div>

                    {/* Products */}
                    <div className="bg-white rounded-2xl border border-gray-100 p-6 mb-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <Package className="w-5 h-5 text-primary" />
                            Productos
                        </h2>

                        {/* Product Search */}
                        <div className="relative mb-4">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                            <input
                                type="text"
                                placeholder="Buscar producto por nombre o SKU..."
                                value={productSearch}
                                onChange={(e) => {
                                    setProductSearch(e.target.value);
                                    setShowProductSearch(e.target.value.length > 0);
                                }}
                                onFocus={() => productSearch.length > 0 && setShowProductSearch(true)}
                                className="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                            />

                            {/* Search Results */}
                            {showProductSearch && filteredProducts.length > 0 && (
                                <div className="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg max-h-60 overflow-y-auto">
                                    {filteredProducts.map(product => (
                                        <button
                                            key={product.id}
                                            type="button"
                                            onClick={() => addProduct(product)}
                                            className="w-full px-4 py-2 text-left hover:bg-gray-50 flex items-center justify-between"
                                        >
                                            <div>
                                                <p className="font-medium text-gray-900">{product.name}</p>
                                                <p className="text-xs text-gray-500">SKU: {product.sku || 'N/A'} | Stock: {product.stock}</p>
                                            </div>
                                            <span className="text-sm text-gray-600">Costo: {formatPrice(product.cost)}</span>
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>

                        {/* Items Table */}
                        {items.length === 0 ? (
                            <div className="text-center py-8 text-gray-500">
                                <Package className="w-12 h-12 mx-auto mb-3 text-gray-300" />
                                <p>No hay productos agregados</p>
                                <p className="text-sm">Busca productos arriba para agregarlos a la factura</p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="bg-gray-50 border-b border-gray-100">
                                            <th className="text-left px-3 py-2 text-xs font-semibold text-gray-500">Producto</th>
                                            <th className="text-center px-3 py-2 text-xs font-semibold text-gray-500 w-24">Cantidad</th>
                                            <th className="text-center px-3 py-2 text-xs font-semibold text-gray-500 w-28">Costo Unit.</th>
                                            <th className="text-center px-3 py-2 text-xs font-semibold text-gray-500 w-24">Margen %</th>
                                            <th className="text-center px-3 py-2 text-xs font-semibold text-gray-500 w-28">Precio Venta</th>
                                            <th className="text-right px-3 py-2 text-xs font-semibold text-gray-500 w-28">Subtotal</th>
                                            <th className="text-center px-3 py-2 text-xs font-semibold text-gray-500 w-16"></th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-50">
                                        {items.map((item, index) => (
                                            <tr key={index}>
                                                <td className="px-3 py-2 text-sm text-gray-900">{item.product_name}</td>
                                                <td className="px-3 py-2">
                                                    <input
                                                        type="number"
                                                        min="0.001"
                                                        step="any"
                                                        value={item.quantity}
                                                        onChange={(e) => updateItem(index, 'quantity', parseFloat(e.target.value) || 0)}
                                                        className="w-full px-2 py-1 text-center border border-gray-200 rounded-lg text-sm"
                                                    />
                                                </td>
                                                <td className="px-3 py-2">
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        step="any"
                                                        value={item.new_cost}
                                                        onChange={(e) => updateItem(index, 'new_cost', parseFloat(e.target.value) || 0)}
                                                        className="w-full px-2 py-1 text-center border border-gray-200 rounded-lg text-sm"
                                                    />
                                                </td>
                                                <td className="px-3 py-2">
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        max="100"
                                                        step="1"
                                                        value={item.margin_percentage || ''}
                                                        onChange={(e) => updateItem(index, 'margin_percentage', e.target.value ? parseFloat(e.target.value) : null)}
                                                        placeholder="%"
                                                        className="w-full px-2 py-1 text-center border border-gray-200 rounded-lg text-sm"
                                                    />
                                                </td>
                                                <td className="px-3 py-2 text-center text-sm text-gray-600">
                                                    {item.calculated_sale_price ? formatPrice(item.calculated_sale_price) : '-'}
                                                </td>
                                                <td className="px-3 py-2 text-right text-sm font-medium text-gray-900">
                                                    {formatPrice(item.quantity * item.new_cost)}
                                                </td>
                                                <td className="px-3 py-2 text-center">
                                                    <button
                                                        type="button"
                                                        onClick={() => removeItem(index)}
                                                        className="p-1 text-gray-400 hover:text-danger transition-colors"
                                                    >
                                                        <Trash2 className="w-4 h-4" />
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>

                    {/* Summary */}
                    <div className="bg-white rounded-2xl border border-gray-100 p-6 mb-6">
                        <div className="flex justify-between items-center max-w-md ml-auto">
                            <div className="space-y-2">
                                <p className="text-sm text-gray-600">Subtotal (Neto):</p>
                                <p className="text-sm text-gray-600">IVA (19%):</p>
                                <p className="text-lg font-bold text-gray-900">Total:</p>
                            </div>
                            <div className="text-right space-y-2">
                                <p className="text-sm text-gray-900">{formatPrice(calculateSubtotal())}</p>
                                <p className="text-sm text-gray-900">{formatPrice(calculateSubtotal() * 0.19)}</p>
                                <p className="text-lg font-bold text-primary">{formatPrice(calculateTotal())}</p>
                            </div>
                        </div>
                    </div>

                    {/* Notes */}
                    <div className="bg-white rounded-2xl border border-gray-100 p-6 mb-6">
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            Notas (opcional)
                        </label>
                        <textarea
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                            rows={3}
                            placeholder="Notas adicionales sobre esta factura..."
                            className="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary resize-none"
                        />
                    </div>

                    {/* Actions */}
                    <div className="flex justify-end gap-4">
                        <Link
                            href={tRoute('purchases.index')}
                            className="px-6 py-2.5 border border-gray-200 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50 transition-colors"
                        >
                            Cancelar
                        </Link>
                        <button
                            type="submit"
                            disabled={processing || items.length === 0}
                            className="inline-flex items-center gap-2 px-6 py-2.5 bg-primary text-white text-sm font-medium rounded-xl hover:bg-primary/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <Save className="w-4 h-4" />
                            {processing ? 'Guardando...' : 'Guardar Factura'}
                        </button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
