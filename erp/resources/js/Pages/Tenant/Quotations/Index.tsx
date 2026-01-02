import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, Link, usePage } from '@inertiajs/react';
import { useTenantRoute } from '@/Hooks/useTenantRoute';
import { useState, useMemo, useCallback } from 'react';
import { toast } from 'sonner';
import {
    Truck,
    Plus,
    Trash2,
    FileText,
    Search,
    Eye,
    Save,
    X,
    Wand2
} from 'lucide-react';

interface Supplier {
    id: number;
    name: string;
    next_quotation_number: number;
}

interface Product {
    id: number;
    code: string;
    name: string;
    stock: number;
    cost_net: number;
    cost_gross: number;
    sale_price: number;
    image: string | null;
}

interface QuotationItem {
    product_id: number;
    code: string;
    name: string;
    quantity: number;
    cost_net_current: number;
    cost_gross_current: number;
    cost_net: number;
    cost_gross: number;
}

interface Quotation {
    id: number;
    quotation_number: string;
    date: string;
    total_amount: number;
    created_by_name: string | null;
}

interface Props {
    suppliers: Supplier[];
    selectedSupplier: Supplier | null;
    products: Product[];
    quotations: Quotation[];
    ivaRate: number;
}

export default function Index({ suppliers, selectedSupplier, products, quotations, ivaRate }: Props) {
    const tRoute = useTenantRoute();
    const { tenant } = usePage().props as any;

    // State
    const [items, setItems] = useState<QuotationItem[]>([]);
    const [showAddModal, setShowAddModal] = useState(false);
    const [showNewProductModal, setShowNewProductModal] = useState(false);
    const [showConfirmModal, setShowConfirmModal] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [saving, setSaving] = useState(false);
    const [quotationDate, setQuotationDate] = useState(new Date().toISOString().split('T')[0]);

    // New product form
    const [newProductCode, setNewProductCode] = useState('');
    const [newProductName, setNewProductName] = useState('');
    const [newProductQuantity, setNewProductQuantity] = useState(1);
    const [newProductCostNet, setNewProductCostNet] = useState(1000);

    const formatCurrency = (amount: number) => {
        return '$' + Math.round(amount).toLocaleString('es-CL');
    };

    // Calculations
    const totals = useMemo(() => {
        const subtotalNet = items.reduce((sum, item) => sum + (item.cost_net * item.quantity), 0);
        const ivaAmount = Math.round(subtotalNet * ivaRate);
        const totalGross = subtotalNet + ivaAmount;
        return { subtotalNet, ivaAmount, totalGross };
    }, [items, ivaRate]);

    // Filter products not yet added
    const availableProducts = useMemo(() => {
        const addedIds = new Set(items.map(i => i.product_id));
        return products.filter(p => !addedIds.has(p.id)).filter(p =>
            !searchQuery ||
            p.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
            p.code?.toLowerCase().includes(searchQuery.toLowerCase())
        );
    }, [products, items, searchQuery]);

    const addProduct = useCallback((product: Product) => {
        setItems(prev => [...prev, {
            product_id: product.id,
            code: product.code || '',
            name: product.name,
            quantity: 1,
            cost_net_current: product.cost_net,
            cost_gross_current: product.cost_gross,
            cost_net: product.cost_net,
            cost_gross: product.cost_gross,
        }]);
        setShowAddModal(false);
        setSearchQuery('');
    }, []);

    const addNewProduct = useCallback(() => {
        if (!newProductName.trim()) {
            toast.error('Ingresa el nombre del producto');
            return;
        }

        const costGross = Math.round(newProductCostNet * (1 + ivaRate));

        setItems(prev => [...prev, {
            product_id: -Date.now(), // Negative ID for new products
            code: newProductCode,
            name: newProductName,
            quantity: newProductQuantity,
            cost_net_current: 0,
            cost_gross_current: 0,
            cost_net: newProductCostNet,
            cost_gross: costGross,
        }]);

        // Reset form
        setNewProductCode('');
        setNewProductName('');
        setNewProductQuantity(1);
        setNewProductCostNet(1000);
        setShowNewProductModal(false);
    }, [newProductCode, newProductName, newProductQuantity, newProductCostNet, ivaRate]);

    const updateItem = useCallback((index: number, field: 'quantity' | 'cost_net', value: number) => {
        setItems(prev => prev.map((item, i) => {
            if (i === index) {
                const updated = { ...item, [field]: value };
                if (field === 'cost_net') {
                    updated.cost_gross = Math.round(value * (1 + ivaRate));
                }
                return updated;
            }
            return item;
        }));
    }, [ivaRate]);

    const removeItem = useCallback((index: number) => {
        setItems(prev => prev.filter((_, i) => i !== index));
    }, []);

    const clearItems = useCallback(() => {
        if (confirm('¿Limpiar toda la cotización?')) {
            setItems([]);
        }
    }, []);

    const saveQuotation = useCallback(async () => {
        if (!selectedSupplier) return;
        if (items.length === 0) {
            toast.error('Agrega al menos un producto');
            return;
        }

        setSaving(true);

        router.post(tRoute('quotations.store'), {
            supplier_id: selectedSupplier.id,
            date: quotationDate,
            items: items.map(item => ({
                product_id: item.product_id,
                code: item.code,
                name: item.name,
                quantity: item.quantity,
                cost_net: item.cost_net,
                cost_gross: item.cost_gross,
            })),
        }, {
            preserveScroll: true,
            onSuccess: (page: any) => {
                const flash = page.props?.flash;
                if (flash?.success) {
                    toast.success(flash.message || 'Cotización generada');
                    setItems([]);
                }
                setSaving(false);
            },
            onError: (errors) => {
                toast.error('Error al generar cotización');
                console.error(errors);
                setSaving(false);
            },
        });
    }, [selectedSupplier, items, quotationDate, tRoute]);

    return (
        <AuthenticatedLayout>
            <Head title="Cotizaciones" />

            <div className="flex h-[calc(100vh-80px)]">
                {/* Sidebar - Suppliers */}
                <aside className="w-64 bg-white border-r border-gray-200 overflow-y-auto flex-shrink-0">
                    <div className="p-4 border-b border-gray-100">
                        <h2 className="font-semibold text-gray-900 flex items-center gap-2">
                            <Truck className="w-4 h-4" />
                            Proveedores
                        </h2>
                    </div>
                    <ul className="divide-y divide-gray-100">
                        {suppliers.length === 0 ? (
                            <li className="p-4 text-gray-500 text-sm">No hay proveedores</li>
                        ) : (
                            suppliers.map(supplier => (
                                <li key={supplier.id}>
                                    <Link
                                        href={tRoute('quotations.index') + `?supplier=${supplier.id}`}
                                        className={`block px-4 py-3 hover:bg-gray-50 ${
                                            selectedSupplier?.id === supplier.id
                                                ? 'bg-primary/5 border-l-4 border-primary'
                                                : ''
                                        }`}
                                    >
                                        <span className="font-medium text-gray-900">{supplier.name}</span>
                                    </Link>
                                </li>
                            ))
                        )}
                    </ul>
                </aside>

                {/* Main Content */}
                <main className="flex-1 overflow-y-auto p-6">
                    {!selectedSupplier ? (
                        <div className="text-center py-20 text-gray-500">
                            <Truck className="w-12 h-12 mx-auto mb-4 opacity-50" />
                            <p>Selecciona un proveedor para comenzar</p>
                        </div>
                    ) : (
                        <div className="space-y-6">
                            {/* Header */}
                            <h1 className="text-xl font-bold text-gray-900">
                                Generar Cotización: {selectedSupplier.name}
                            </h1>

                            {/* Form Card */}
                            <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                                <div className="p-4 border-b border-gray-100">
                                    <div className="flex items-center justify-between">
                                        <h2 className="font-semibold">Productos en la Cotización</h2>
                                        <div className="flex gap-2">
                                            <button
                                                onClick={() => setShowAddModal(true)}
                                                className="px-3 py-1.5 border border-gray-300 rounded-lg text-sm hover:bg-gray-50 flex items-center gap-1"
                                            >
                                                <Plus className="w-4 h-4" />
                                                Agregar Producto Existente
                                            </button>
                                            <button
                                                onClick={() => setShowNewProductModal(true)}
                                                className="px-3 py-1.5 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700 flex items-center gap-1"
                                            >
                                                <Wand2 className="w-4 h-4" />
                                                Agregar Producto Nuevo (Solo Cotización)
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                {/* Table */}
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th rowSpan={2} className="px-3 py-2 text-left font-medium text-gray-600 border-b">Código</th>
                                                <th rowSpan={2} className="px-3 py-2 text-left font-medium text-gray-600 border-b">Producto</th>
                                                <th colSpan={2} className="px-3 py-1 text-center font-medium text-gray-500 bg-gray-100 border-b text-xs">Costos Registrados</th>
                                                <th colSpan={3} className="px-3 py-1 text-center font-medium text-blue-700 bg-blue-50 border-b text-xs">Datos a Cotizar</th>
                                                <th rowSpan={2} className="px-3 py-2 text-center font-medium text-gray-600 border-b">Acción</th>
                                            </tr>
                                            <tr className="bg-gray-50">
                                                <th className="px-3 py-2 text-right font-medium text-gray-500 text-xs border-b">Costo Neto Act.</th>
                                                <th className="px-3 py-2 text-right font-medium text-gray-500 text-xs border-b">Costo Bruto Act.</th>
                                                <th className="px-3 py-2 text-right font-medium text-blue-700 text-xs border-b">Costo Neto Cotizado *</th>
                                                <th className="px-3 py-2 text-right font-medium text-blue-700 text-xs border-b">Costo Bruto Cotizado</th>
                                                <th className="px-3 py-2 text-center font-medium text-blue-700 text-xs border-b">Cantidad a Cotizar *</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-100">
                                            {items.length === 0 ? (
                                                <tr>
                                                    <td colSpan={8} className="px-4 py-8 text-center text-gray-400">
                                                        Para empezar, agrega un producto nuevo o existente
                                                    </td>
                                                </tr>
                                            ) : (
                                                items.map((item, index) => (
                                                    <tr key={index} className="hover:bg-gray-50">
                                                        <td className="px-3 py-2 font-mono text-xs">{item.code || '-'}</td>
                                                        <td className="px-3 py-2">{item.name}</td>
                                                        <td className="px-3 py-2 text-right text-gray-500">
                                                            {item.cost_net_current > 0 ? formatCurrency(item.cost_net_current) : '-'}
                                                        </td>
                                                        <td className="px-3 py-2 text-right text-gray-500">
                                                            {item.cost_gross_current > 0 ? formatCurrency(item.cost_gross_current) : '-'}
                                                        </td>
                                                        <td className="px-3 py-2 text-right">
                                                            <input
                                                                type="number"
                                                                value={item.cost_net}
                                                                onChange={(e) => updateItem(index, 'cost_net', parseInt(e.target.value) || 0)}
                                                                className="w-24 text-right px-2 py-1 border rounded text-sm"
                                                            />
                                                        </td>
                                                        <td className="px-3 py-2 text-right text-gray-600">
                                                            {formatCurrency(item.cost_gross)}
                                                        </td>
                                                        <td className="px-3 py-2 text-center">
                                                            <input
                                                                type="number"
                                                                value={item.quantity}
                                                                onChange={(e) => updateItem(index, 'quantity', parseInt(e.target.value) || 1)}
                                                                className="w-16 text-center px-2 py-1 border rounded text-sm"
                                                                min={1}
                                                            />
                                                        </td>
                                                        <td className="px-3 py-2 text-center">
                                                            <button onClick={() => removeItem(index)} className="p-1 text-red-500 hover:bg-red-50 rounded">
                                                                <Trash2 className="w-4 h-4" />
                                                            </button>
                                                        </td>
                                                    </tr>
                                                ))
                                            )}
                                        </tbody>
                                    </table>
                                </div>

                                {/* Totals */}
                                <div className="p-4 bg-gray-50 border-t text-right space-y-1">
                                    <p className="text-sm text-gray-600">Subtotal (Neto): <span className="font-medium">{formatCurrency(totals.subtotalNet)}</span></p>
                                    <p className="text-sm text-gray-600">IVA ({(ivaRate * 100).toFixed(0)}%): <span className="font-medium">{formatCurrency(totals.ivaAmount)}</span></p>
                                    <p className="text-lg font-bold text-green-600">Total Cotización (Bruto): {formatCurrency(totals.totalGross)}</p>
                                </div>

                                {/* Actions */}
                                <div className="p-4 border-t flex items-center justify-between">
                                    <button
                                        onClick={clearItems}
                                        disabled={items.length === 0}
                                        className="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 flex items-center gap-2"
                                    >
                                        <Trash2 className="w-4 h-4" />
                                        Limpiar Cotización
                                    </button>
                                    <button
                                        onClick={() => setShowConfirmModal(true)}
                                        disabled={items.length === 0}
                                        className="px-6 py-2 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700 disabled:opacity-50 flex items-center gap-2"
                                    >
                                        <FileText className="w-4 h-4" />
                                        Generar Cotización
                                    </button>
                                </div>
                            </div>

                            {/* History */}
                            <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                                <div className="p-4 border-b border-gray-100">
                                    <h2 className="font-semibold text-gray-900">Historial de Cotizaciones</h2>
                                </div>
                                <table className="w-full text-sm">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-4 py-3 text-left font-medium text-gray-600">Nº Cotización</th>
                                            <th className="px-4 py-3 text-left font-medium text-gray-600">Proveedor</th>
                                            <th className="px-4 py-3 text-left font-medium text-gray-600">Fecha de Emisión</th>
                                            <th className="px-4 py-3 text-right font-medium text-gray-600">Monto</th>
                                            <th className="px-4 py-3 text-left font-medium text-gray-600">Creada por</th>
                                            <th className="px-4 py-3 text-center font-medium text-gray-600">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {quotations.length === 0 ? (
                                            <tr>
                                                <td colSpan={6} className="px-4 py-8 text-center text-gray-400">
                                                    No hay cotizaciones registradas para este proveedor.
                                                </td>
                                            </tr>
                                        ) : (
                                            quotations.map(q => (
                                                <tr key={q.id} className="hover:bg-gray-50">
                                                    <td className="px-4 py-3 font-mono font-medium">{q.quotation_number}</td>
                                                    <td className="px-4 py-3">{selectedSupplier.name}</td>
                                                    <td className="px-4 py-3">{new Date(q.date).toLocaleDateString('es-CL')}</td>
                                                    <td className="px-4 py-3 text-right font-medium">{formatCurrency(q.total_amount)}</td>
                                                    <td className="px-4 py-3 text-gray-500">{q.created_by_name || '-'}</td>
                                                    <td className="px-4 py-3 text-center">
                                                        <button
                                                            onClick={() => window.open(`/quotations/view/${q.id}`, '_blank')}
                                                            className="px-3 py-1 text-primary text-xs hover:bg-primary/5 rounded flex items-center gap-1 mx-auto"
                                                        >
                                                            <Eye className="w-3 h-3" />
                                                            Ver Detalle
                                                        </button>
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}
                </main>
            </div>

            {/* Add Existing Product Modal */}
            {showAddModal && (
                <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                    <div className="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[80vh] overflow-hidden">
                        <div className="p-4 border-b flex items-center justify-between">
                            <h3 className="font-semibold text-lg">Seleccionar Producto Existente</h3>
                            <button onClick={() => setShowAddModal(false)} className="p-1 hover:bg-gray-100 rounded">
                                <X className="w-5 h-5" />
                            </button>
                        </div>
                        <div className="p-4">
                            <p className="text-sm text-gray-500 mb-4">Haz clic en las tarjetas de producto que deseas añadir a tu cotización. Los ya agregados están desactivados.</p>
                            <div className="max-h-96 overflow-y-auto grid grid-cols-2 gap-3">
                                {availableProducts.length === 0 ? (
                                    <p className="col-span-2 text-center py-8 text-gray-400">No hay productos disponibles</p>
                                ) : (
                                    availableProducts.map(product => (
                                        <button
                                            key={product.id}
                                            onClick={() => addProduct(product)}
                                            className="p-3 border rounded-lg hover:bg-gray-50 text-left flex items-center gap-3"
                                        >
                                            {product.image ? (
                                                <img src={product.image} className="w-12 h-12 object-cover rounded" />
                                            ) : (
                                                <div className="w-12 h-12 bg-gray-100 rounded flex items-center justify-center text-gray-400 text-xs">N/A</div>
                                            )}
                                            <div className="flex-1 min-w-0">
                                                <p className="font-medium truncate">{product.name}</p>
                                                <p className="text-xs text-gray-500">{product.code} · Stock: {product.stock}</p>
                                            </div>
                                        </button>
                                    ))
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Add New Product Modal */}
            {showNewProductModal && (
                <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                    <div className="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">
                        <div className="p-4 border-b flex items-center justify-between">
                            <h3 className="font-semibold text-lg">Crear y Añadir Nuevo Producto (Solo Cotización)</h3>
                            <button onClick={() => setShowNewProductModal(false)} className="p-1 hover:bg-gray-100 rounded">
                                <X className="w-5 h-5" />
                            </button>
                        </div>
                        <div className="p-4 space-y-4">
                            <p className="text-sm text-gray-500">Este producto <strong>no</strong> se registrará en la tabla 'products', solo se usará para esta cotización.</p>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Código de barras:</label>
                                <input
                                    type="text"
                                    value={newProductCode}
                                    onChange={(e) => setNewProductCode(e.target.value)}
                                    className="w-full px-3 py-2 border rounded-lg"
                                />
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Nombre del Producto:</label>
                                <input
                                    type="text"
                                    value={newProductName}
                                    onChange={(e) => setNewProductName(e.target.value)}
                                    className="w-full px-3 py-2 border rounded-lg"
                                    required
                                />
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Cantidad a Pedir:</label>
                                <input
                                    type="number"
                                    value={newProductQuantity}
                                    onChange={(e) => setNewProductQuantity(parseInt(e.target.value) || 1)}
                                    className="w-full px-3 py-2 border rounded-lg"
                                    min={1}
                                />
                            </div>

                            <hr />

                            <h4 className="font-medium">Definición de Costo de Compra</h4>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Costo Neto (Inicial):</label>
                                <input
                                    type="number"
                                    value={newProductCostNet}
                                    onChange={(e) => setNewProductCostNet(parseInt(e.target.value) || 0)}
                                    className="w-full px-3 py-2 border rounded-lg"
                                    min={0}
                                />
                                <p className="text-sm text-gray-500 mt-1">
                                    Costo Bruto estimado: {formatCurrency(Math.round(newProductCostNet * (1 + ivaRate)))}
                                </p>
                            </div>

                            <button
                                onClick={addNewProduct}
                                className="w-full py-2 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700 flex items-center justify-center gap-2"
                            >
                                <Plus className="w-4 h-4" />
                                Crear y añadir a cotización
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Confirm Modal */}
            {showConfirmModal && (
                <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                    <div className="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">
                        <div className="p-4 border-b flex items-center justify-between">
                            <h3 className="font-semibold text-lg">Confirmar y Generar Cotización</h3>
                            <button onClick={() => setShowConfirmModal(false)} className="p-1 hover:bg-gray-100 rounded">
                                <X className="w-5 h-5" />
                            </button>
                        </div>
                        <div className="p-6 space-y-4">
                            <p className="text-gray-600">Estás a punto de generar la cotización para este proveedor.</p>

                            <p className="text-lg">
                                Total Cotización a Registrar (Bruto): <span className="text-2xl font-bold text-green-600">{formatCurrency(totals.totalGross)}</span>
                            </p>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Fecha de Emisión:</label>
                                <input
                                    type="date"
                                    value={quotationDate}
                                    onChange={(e) => setQuotationDate(e.target.value)}
                                    className="w-full px-3 py-2 border rounded-lg"
                                />
                            </div>

                            <button
                                onClick={() => { setShowConfirmModal(false); saveQuotation(); }}
                                disabled={saving}
                                className="w-full py-3 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700 disabled:opacity-50 flex items-center justify-center gap-2"
                            >
                                <Save className="w-4 h-4" />
                                {saving ? 'Guardando...' : 'Generar y Guardar Cotización'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
