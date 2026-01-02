import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useTenantRoute } from '@/Hooks/useTenantRoute';
import { useState, useCallback, useMemo } from 'react';
import { toast } from 'sonner';
import {
    Package,
    Percent,
    DollarSign,
    Search,
    Plus,
    Minus,
    Trash2,
    Edit,
    X,
    Save
} from 'lucide-react';

interface Offer {
    id: number;
    name: string;
    barcode: string;
    price: number;
    cost: number;
    stock: number;
    margin_percent: number;
}

interface Product {
    id: number;
    name: string;
    barcode: string;
    price: number;
    cost: number;
    stock: number;
    image: string | null;
}

interface OfferItem {
    id: number;
    name: string;
    stock: number;
    cost: number;
    quantity: number;
    original_price: number;
    offer_price: number;
    discount_percent: number;
}

interface KPIs {
    available_offers: number;
    total_stock: number;
    total_retail_value: number;
}

interface Props {
    kpis: KPIs;
    offers: Offer[];
}

export default function Index({ kpis, offers }: Props) {
    const tRoute = useTenantRoute();

    // Form state
    const [editingId, setEditingId] = useState<number | null>(null);
    const [name, setName] = useState('');
    const [barcode, setBarcode] = useState('');
    const [stock, setStock] = useState(0);
    const [finalPrice, setFinalPrice] = useState(0);
    const [items, setItems] = useState<OfferItem[]>([]);

    // Search state
    const [searchQuery, setSearchQuery] = useState('');
    const [searchResults, setSearchResults] = useState<Product[]>([]);
    const [searching, setSearching] = useState(false);

    // UI state
    const [saving, setSaving] = useState(false);
    const [deleting, setDeleting] = useState<number | null>(null);

    const formatCurrency = (amount: number) => {
        return '$' + Math.round(amount).toLocaleString('es-CL');
    };

    // Calculated values
    const calculations = useMemo(() => {
        const sumOriginal = items.reduce((sum, item) => sum + (item.original_price * item.quantity), 0);
        const sumOffer = items.reduce((sum, item) => sum + (item.offer_price * item.quantity), 0);
        const totalCost = items.reduce((sum, item) => sum + (item.cost * item.quantity), 0);
        const discount = sumOriginal - sumOffer;
        const grossProfit = finalPrice - totalCost;
        const marginPercent = finalPrice > 0 ? ((finalPrice - totalCost) / finalPrice) * 100 : 0;
        const savingsPercent = sumOriginal > 0 ? (discount / sumOriginal) * 100 : 0;

        return {
            sumOriginal,
            sumOffer,
            totalCost,
            discount,
            grossProfit,
            marginPercent,
            savingsPercent,
        };
    }, [items, finalPrice]);

    const searchProducts = useCallback(async (query: string) => {
        if (query.length < 2) {
            setSearchResults([]);
            return;
        }

        setSearching(true);
        try {
            const response = await fetch(tRoute('offers.search') + `?q=${encodeURIComponent(query)}`);
            const data = await response.json();
            setSearchResults(data.products || []);
        } catch (error) {
            console.error('Search error:', error);
        } finally {
            setSearching(false);
        }
    }, [tRoute]);

    const addProduct = useCallback((product: Product) => {
        if (items.some(item => item.id === product.id)) {
            toast.error('Este producto ya está en el pack');
            return;
        }

        setItems(prev => [...prev, {
            id: product.id,
            name: product.name,
            stock: product.stock,
            cost: product.cost,
            quantity: 1,
            original_price: product.price,
            offer_price: product.price,
            discount_percent: 0,
        }]);

        setSearchQuery('');
        setSearchResults([]);
    }, [items]);

    const updateItemQuantity = useCallback((id: number, delta: number) => {
        setItems(prev => prev.map(item => {
            if (item.id === id) {
                const newQty = Math.max(1, item.quantity + delta);
                return { ...item, quantity: newQty };
            }
            return item;
        }));
    }, []);

    const updateItemDiscount = useCallback((id: number, discount: number) => {
        setItems(prev => prev.map(item => {
            if (item.id === id) {
                const discountClamped = Math.min(100, Math.max(0, discount));
                const offerPrice = item.original_price * (1 - discountClamped / 100);
                return { ...item, discount_percent: discountClamped, offer_price: Math.round(offerPrice) };
            }
            return item;
        }));
    }, []);

    const removeItem = useCallback((id: number) => {
        setItems(prev => prev.filter(item => item.id !== id));
    }, []);

    const clearForm = useCallback(() => {
        setEditingId(null);
        setName('');
        setBarcode('');
        setStock(0);
        setFinalPrice(0);
        setItems([]);
    }, []);

    const loadOffer = useCallback(async (id: number) => {
        try {
            const response = await fetch(tRoute('offers.show', { id }));
            const data = await response.json();

            if (data.success) {
                setEditingId(id);
                setName(data.offer.name);
                setBarcode(data.offer.barcode || '');
                setStock(data.offer.stock);
                setFinalPrice(data.offer.price);
                setItems(data.items);
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        } catch (error) {
            toast.error('Error al cargar la oferta');
        }
    }, [tRoute]);

    const saveOffer = useCallback(() => {
        if (!name.trim()) {
            toast.error('Ingresa un nombre para la oferta');
            return;
        }
        if (items.length === 0) {
            toast.error('Agrega al menos un producto');
            return;
        }
        if (finalPrice <= 0) {
            toast.error('Ingresa un precio final válido');
            return;
        }

        setSaving(true);
        const payload = {
            name,
            barcode,
            price: finalPrice,
            stock,
            items: items.map(item => ({
                id: item.id,
                quantity: item.quantity,
                original_price: item.original_price,
                offer_price: item.offer_price,
            })),
        };

        if (editingId) {
            router.put(tRoute('offers.update', { id: editingId }), payload, {
                onSuccess: () => {
                    toast.success('Oferta actualizada');
                    clearForm();
                },
                onError: () => toast.error('Error al actualizar'),
                onFinish: () => setSaving(false),
            });
        } else {
            router.post(tRoute('offers.store'), payload, {
                onSuccess: () => {
                    toast.success('Oferta creada');
                    clearForm();
                },
                onError: () => toast.error('Error al crear'),
                onFinish: () => setSaving(false),
            });
        }
    }, [name, barcode, finalPrice, stock, items, editingId, tRoute, clearForm]);

    const deleteOffer = useCallback((id: number) => {
        if (!confirm('¿Eliminar esta oferta?')) return;

        setDeleting(id);
        router.delete(tRoute('offers.destroy', { id }), {
            onSuccess: () => toast.success('Oferta eliminada'),
            onError: () => toast.error('Error al eliminar'),
            onFinish: () => setDeleting(null),
        });
    }, [tRoute]);

    // Auto-update final price when items change
    const suggestedPrice = useMemo(() => calculations.sumOffer, [calculations.sumOffer]);

    const KpiCard = ({ title, value, icon: Icon, color }: { title: string; value: string | number; icon: any; color: string }) => (
        <div className={`bg-white rounded-xl p-4 shadow-sm border-l-4 ${color}`}>
            <div className="flex items-start justify-between">
                <div>
                    <p className="text-xs text-gray-500 mb-1">{title}</p>
                    <p className="text-xl font-bold text-gray-900">{value}</p>
                </div>
                <Icon className="w-5 h-5 text-gray-400" />
            </div>
        </div>
    );

    return (
        <AuthenticatedLayout>
            <Head title="Ofertas y Packs" />

            <div className="py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
                {/* Header */}
                <div className="mb-6">
                    <h1 className="text-2xl font-bold text-gray-900">🎁 Ofertas y Packs</h1>
                    <p className="text-sm text-gray-500">Crea bundles de productos con descuentos</p>
                </div>

                {/* KPIs */}
                <div className="grid grid-cols-3 gap-4 mb-6">
                    <KpiCard title="Ofertas Disponibles" value={kpis.available_offers} icon={Package} color="border-blue-500" />
                    <KpiCard title="Stock Total Packs" value={kpis.total_stock} icon={Package} color="border-green-500" />
                    <KpiCard title="Valor Retail" value={formatCurrency(kpis.total_retail_value)} icon={DollarSign} color="border-purple-500" />
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* Pack Builder */}
                    <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h2 className="text-lg font-semibold mb-4">
                            {editingId ? '✏️ Editar Oferta' : '➕ Nueva Oferta'}
                        </h2>

                        {/* Search Products */}
                        <div className="relative mb-4">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                            <input
                                type="text"
                                placeholder="Buscar productos..."
                                value={searchQuery}
                                onChange={(e) => {
                                    setSearchQuery(e.target.value);
                                    searchProducts(e.target.value);
                                }}
                                className="w-full pl-10 pr-4 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-primary/20"
                            />
                            {searchResults.length > 0 && (
                                <div className="absolute top-full left-0 right-0 mt-1 bg-white rounded-lg shadow-lg border z-50 max-h-60 overflow-auto">
                                    {searchResults.map(product => (
                                        <button
                                            key={product.id}
                                            onClick={() => addProduct(product)}
                                            className="w-full px-4 py-2 text-left hover:bg-gray-50 flex justify-between items-center"
                                        >
                                            <span className="text-sm font-medium">{product.name}</span>
                                            <span className="text-sm text-gray-500">{formatCurrency(product.price)}</span>
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>

                        {/* Offer Name & Stock */}
                        <div className="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label className="block text-xs font-medium text-gray-500 mb-1">Nombre</label>
                                <input
                                    type="text"
                                    value={name}
                                    onChange={(e) => setName(e.target.value)}
                                    placeholder="Ej: Pack Ahorro Verano"
                                    className="w-full px-3 py-2 border rounded-lg text-sm"
                                />
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-gray-500 mb-1">Stock</label>
                                <input
                                    type="number"
                                    value={stock}
                                    onChange={(e) => setStock(parseInt(e.target.value) || 0)}
                                    className="w-full px-3 py-2 border rounded-lg text-sm"
                                    min={0}
                                />
                            </div>
                        </div>

                        {/* Items Table */}
                        {items.length > 0 && (
                            <div className="border rounded-lg overflow-hidden mb-4">
                                <table className="w-full text-sm">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-3 py-2 text-left font-medium text-gray-600">Producto</th>
                                            <th className="px-3 py-2 text-center font-medium text-gray-600">Cant.</th>
                                            <th className="px-3 py-2 text-center font-medium text-gray-600">Dcto %</th>
                                            <th className="px-3 py-2 text-center font-medium text-gray-600">P. Oferta</th>
                                            <th className="px-3 py-2"></th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {items.map(item => (
                                            <tr key={item.id}>
                                                <td className="px-3 py-2">
                                                    <div className="font-medium text-gray-900 text-xs">{item.name}</div>
                                                    <div className="text-gray-400 text-xs">Original: {formatCurrency(item.original_price)}</div>
                                                </td>
                                                <td className="px-3 py-2">
                                                    <div className="flex items-center justify-center gap-1">
                                                        <button onClick={() => updateItemQuantity(item.id, -1)} className="p-1 hover:bg-gray-100 rounded"><Minus className="w-3 h-3" /></button>
                                                        <span className="w-6 text-center text-xs">{item.quantity}</span>
                                                        <button onClick={() => updateItemQuantity(item.id, 1)} className="p-1 hover:bg-gray-100 rounded"><Plus className="w-3 h-3" /></button>
                                                    </div>
                                                </td>
                                                <td className="px-3 py-2">
                                                    <input
                                                        type="number"
                                                        value={item.discount_percent}
                                                        onChange={(e) => updateItemDiscount(item.id, parseFloat(e.target.value) || 0)}
                                                        className="w-14 text-center px-1 py-1 border rounded text-xs"
                                                        min={0}
                                                        max={100}
                                                    />
                                                </td>
                                                <td className="px-3 py-2 text-center text-xs text-green-600 font-medium">{formatCurrency(item.offer_price)}</td>
                                                <td className="px-3 py-2">
                                                    <button onClick={() => removeItem(item.id)} className="p-1 text-red-500 hover:bg-red-50 rounded"><Trash2 className="w-4 h-4" /></button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        {/* Summary */}
                        {items.length > 0 && (
                            <div className="bg-gray-50 rounded-lg p-4 mb-4 space-y-2 text-sm">
                                <div className="flex justify-between"><span className="text-gray-500">Precio Original:</span><span>{formatCurrency(calculations.sumOriginal)}</span></div>
                                <div className="flex justify-between"><span className="text-gray-500">Costo Total:</span><span>{formatCurrency(calculations.totalCost)}</span></div>
                                <div className="flex justify-between text-red-600"><span>Descuento:</span><span>-{formatCurrency(calculations.discount)}</span></div>
                                <hr />
                                <div className="flex justify-between font-bold"><span>Precio Sugerido:</span><span className="text-green-600">{formatCurrency(suggestedPrice)}</span></div>
                            </div>
                        )}

                        {/* Final Price */}
                        <div className="mb-4">
                            <label className="block text-xs font-medium text-gray-500 mb-1">Precio Final de Venta</label>
                            <div className="flex items-center gap-2">
                                <span className="text-lg font-bold text-gray-400">$</span>
                                <input
                                    type="number"
                                    value={finalPrice}
                                    onChange={(e) => setFinalPrice(parseInt(e.target.value) || 0)}
                                    className="flex-1 px-3 py-2 border-2 border-primary rounded-lg text-lg font-bold"
                                />
                                <button
                                    onClick={() => setFinalPrice(suggestedPrice)}
                                    className="px-3 py-2 bg-gray-100 rounded-lg text-xs hover:bg-gray-200"
                                >
                                    Usar sugerido
                                </button>
                            </div>
                        </div>

                        {/* KPI Cards */}
                        {items.length > 0 && finalPrice > 0 && (
                            <div className="grid grid-cols-3 gap-2 mb-4">
                                <div className="bg-green-50 rounded-lg p-3 text-center">
                                    <div className="text-xs text-green-700">Ganancia</div>
                                    <div className="font-bold text-green-800">{formatCurrency(calculations.grossProfit)}</div>
                                </div>
                                <div className="bg-blue-50 rounded-lg p-3 text-center">
                                    <div className="text-xs text-blue-700">Margen</div>
                                    <div className="font-bold text-blue-800">{calculations.marginPercent.toFixed(1)}%</div>
                                </div>
                                <div className="bg-purple-50 rounded-lg p-3 text-center">
                                    <div className="text-xs text-purple-700">Ahorro Cliente</div>
                                    <div className="font-bold text-purple-800">{calculations.savingsPercent.toFixed(1)}%</div>
                                </div>
                            </div>
                        )}

                        {/* Actions */}
                        <div className="flex gap-2">
                            <button
                                onClick={saveOffer}
                                disabled={saving || items.length === 0}
                                className="flex-1 py-2 bg-primary text-white rounded-lg font-medium hover:bg-primary/90 disabled:opacity-50 flex items-center justify-center gap-2"
                            >
                                <Save className="w-4 h-4" />
                                {saving ? 'Guardando...' : editingId ? 'Actualizar' : 'Crear Oferta'}
                            </button>
                            {editingId && (
                                <button onClick={clearForm} className="px-4 py-2 bg-gray-100 rounded-lg hover:bg-gray-200">
                                    <X className="w-4 h-4" />
                                </button>
                            )}
                        </div>
                    </div>

                    {/* Existing Offers */}
                    <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h2 className="text-lg font-semibold mb-4">📋 Ofertas Existentes</h2>

                        {offers.length === 0 ? (
                            <div className="text-center py-12 text-gray-400">
                                <Package className="w-12 h-12 mx-auto mb-2 opacity-50" />
                                <p>No hay ofertas creadas</p>
                            </div>
                        ) : (
                            <div className="space-y-3">
                                {offers.map(offer => (
                                    <div key={offer.id} className="border rounded-lg p-4 hover:bg-gray-50">
                                        <div className="flex justify-between items-start mb-2">
                                            <div>
                                                <h3 className="font-medium text-gray-900">{offer.name}</h3>
                                                <p className="text-xs text-gray-400">{offer.barcode}</p>
                                            </div>
                                            <div className="flex gap-1">
                                                <button
                                                    onClick={() => loadOffer(offer.id)}
                                                    className="p-2 text-blue-600 hover:bg-blue-50 rounded"
                                                >
                                                    <Edit className="w-4 h-4" />
                                                </button>
                                                <button
                                                    onClick={() => deleteOffer(offer.id)}
                                                    disabled={deleting === offer.id}
                                                    className="p-2 text-red-600 hover:bg-red-50 rounded disabled:opacity-50"
                                                >
                                                    <Trash2 className="w-4 h-4" />
                                                </button>
                                            </div>
                                        </div>
                                        <div className="grid grid-cols-4 gap-2 text-xs">
                                            <div>
                                                <span className="text-gray-400">Stock</span>
                                                <p className="font-medium">{offer.stock}</p>
                                            </div>
                                            <div>
                                                <span className="text-gray-400">Costo</span>
                                                <p className="font-medium">{formatCurrency(offer.cost)}</p>
                                            </div>
                                            <div>
                                                <span className="text-gray-400">Precio</span>
                                                <p className="font-medium text-green-600">{formatCurrency(offer.price)}</p>
                                            </div>
                                            <div>
                                                <span className="text-gray-400">Margen</span>
                                                <p className={`font-medium ${offer.margin_percent < 10 ? 'text-red-600' : offer.margin_percent > 30 ? 'text-green-600' : 'text-blue-600'}`}>
                                                    {offer.margin_percent}%
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
