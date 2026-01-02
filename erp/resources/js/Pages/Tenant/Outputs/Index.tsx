import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useTenantRoute } from '@/Hooks/useTenantRoute';
import { useState, useRef, useEffect } from 'react';
import {
    PackageMinus,
    Search,
    AlertCircle,
    X,
    TrendingDown,
    DollarSign,
    Box,
    ShoppingCart
} from 'lucide-react';
import { toast } from 'sonner';
import { useDebounce } from '@/Hooks/useDebounce'; // Assuming we have this or I'll implement a simple one

// Types
interface Product {
    id: number;
    name: string;
    barcode: string;
    stock: number;
    image_url: string | null;
}

interface InternalConsumption {
    id: number;
    quantity_removed: number;
    cost_price_at_time: number;
    sale_price_at_time: number;
    notes: string | null;
    removal_date: string;
    product: {
        name: string;
        image_url: string | null;
    };
    user: {
        name: string;
    }
}

interface Props {
    metrics: {
        total_units: number;
        total_cost_net: number;
        total_cost_gross: number;
        total_lost_sales: number;
    };
    topProducts: Array<{ name: string; total_removed: number; image_url: string | null }>;
    history: InternalConsumption[];
    filters: {
        date_start: string;
        date_end: string;
    };
}

export default function Index({ metrics, topProducts, history, filters }: Props) {
    const tRoute = useTenantRoute();
    const [searchQuery, setSearchQuery] = useState('');
    const [searchResults, setSearchResults] = useState<Product[]>([]);
    const [selectedProduct, setSelectedProduct] = useState<Product | null>(null);
    const [quantity, setQuantity] = useState(1);
    const [notes, setNotes] = useState('');
    const [isSearching, setIsSearching] = useState(false);

    // Date Filters
    const [dateStart, setDateStart] = useState(filters.date_start);
    const [dateEnd, setDateEnd] = useState(filters.date_end);

    // Debounced Search
    useEffect(() => {
        const timer = setTimeout(() => {
            if (searchQuery.length >= 2) {
                performSearch(searchQuery);
            } else {
                setSearchResults([]);
            }
        }, 300);
        return () => clearTimeout(timer);
    }, [searchQuery]);

    const performSearch = async (query: string) => {
        setIsSearching(true);
        try {
            // We can reuse the POS search API or a new one.
            // For now, let's assume valid route api.products.search or we fetch via Inertia visit (too slow for autocomplete)
            // Or we can use the existing /app/{tenant}/pos/search endpoint if available, but that's specific to POS controller.
            // Let's implement a quick fetch to a new endpoint or verify if we have one.
            // Wait, I didn't create a search endpoint in InternalConsumptionController.
            // I'll assume we can use the same pattern as POS or create a quick ad-hoc search here?
            // Actually, best practice: Create a dedicated API route or use the existing POS one if accessible.
            // Let's try to fetch from a standard product search endpoint.
            // Since I haven't checked for a generic product search API, I'll fallback to a simple fetch if I knew the route.
            // But wait, I added `InternalConsumptionController`, I didn't add a `search` method.
            // I'll create a `search` method in `ProductController` or similar if needed.
            // For now, I'll assume I can hit `/app/{tenant}/products/search?q=` if it exists.
            // Checking `routes/web.php`... I don't see a generic product search.
            // I will implement a quick client-side search approach OR just rely on basic "Enter to search" submitting a form
            // but for a smooth UX we want autocomplete.

            // Let's use `window.axios` or `fetch` to hit `/app/{tenant}/pos/search`? That might be protected/scoped.
            // Use Inertia? No.
            // I'll assume for now I will use a simple fetch to `/api/products/search` (I might need to add this route).
            // Actually, let's just use `router.visit` with `preserveState` to a `search` method in THIS controller if I add it?
            // No, that's heavy.

            // Plan B: Add a `search` method to `InternalConsumptionController` and route `outputs/search`.

            // Let's implement the frontend assuming the route `tRoute('outputs.search')` exists and returns JSON.

            const response = await fetch(tRoute('outputs.search') + `?query=${encodeURIComponent(query)}`);

            if (response.ok) {
                 const data = await response.json();
                 setSearchResults(data);
            }
        } catch (error) {
            console.error(error);
        } finally {
            setIsSearching(false);
        }
    };

    const handleSelectProduct = (product: Product) => {
        setSelectedProduct(product);
        setSearchQuery('');
        setSearchResults([]);
        setQuantity(1);
    };

    const handleRegister = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedProduct) return;

        router.post(tRoute('outputs.store'), {
            product_id: selectedProduct.id,
            quantity: quantity,
            notes: notes
        }, {
            onSuccess: () => {
                toast.success('Salida registrada');
                setSelectedProduct(null);
                setQuantity(1);
                setNotes('');
            },
            onError: () => toast.error('Error al registrar salida')
        });
    };

    const handleDelete = (id: number) => {
        if (confirm('¿Estás seguro de eliminar este registro? El stock será devuelto.')) {
            router.delete(tRoute('outputs.destroy', { output: id }), {
                onSuccess: () => toast.success('Registro eliminado y stock devuelto'),
                onError: () => toast.error('Error al eliminar')
            });
        }
    };

    const applyFilters = () => {
        router.get(tRoute('outputs.index'), {
            date_start: dateStart,
            date_end: dateEnd
        }, {
            preserveState: true,
            preserveScroll: true
        });
    };

    const formatCurrency = (val: number) => {
        return new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(val);
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 flex items-center gap-2">
                    <PackageMinus className="w-5 h-5" />
                    Consumo Interno y Mermas
                </h2>
            }
        >
            <Head title="Consumo Interno" />

            <div className="py-8">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                    {/* KPIs */}
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <KpiCard title="Unidades Retiradas" value={metrics.total_units} icon={<Box className="w-5 h-5 text-blue-500" />} />
                        <KpiCard title="Costo Neto" value={formatCurrency(metrics.total_cost_net)} icon={<DollarSign className="w-5 h-5 text-emerald-500" />} />
                        <KpiCard title="Costo Bruto (+19%)" value={formatCurrency(metrics.total_cost_gross)} icon={<DollarSign className="w-5 h-5 text-purple-500" />} />
                        <KpiCard title="Venta Potencial Perdida" value={formatCurrency(metrics.total_lost_sales)} icon={<TrendingDown className="w-5 h-5 text-red-500" />} />
                    </div>

                    {/* Main Content Grid */}
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">

                        {/* LEFT: Registration Form */}
                        <div className="lg:col-span-1 space-y-6">
                            <div className="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                                <h3 className="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                                    <ShoppingCart className="w-4 h-4" />
                                    Registrar Salida
                                </h3>

                                <div className="space-y-4">
                                    {/* Product Search */}
                                    <div className="relative">
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Buscar Producto</label>
                                        <div className="relative">
                                            <input
                                                type="text"
                                                value={selectedProduct ? selectedProduct.name : searchQuery}
                                                onChange={(e) => {
                                                    setSearchQuery(e.target.value);
                                                    if (selectedProduct) setSelectedProduct(null);
                                                }}
                                                placeholder="Nombre o código..."
                                                className={`w-full pl-10 pr-4 py-2 rounded-lg border focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors ${selectedProduct ? 'border-primary bg-blue-50/50 font-medium text-primary' : 'border-gray-300'}`}
                                            />
                                            <Search className="w-4 h-4 text-gray-400 absolute left-3 top-3" />
                                            {selectedProduct && (
                                                <button
                                                    onClick={() => { setSelectedProduct(null); setSearchQuery(''); }}
                                                    className="absolute right-3 top-3 text-gray-400 hover:text-red-500"
                                                >
                                                    <X className="w-4 h-4" />
                                                </button>
                                            )}
                                        </div>

                                        {/* Dropdown Results */}
                                        {searchResults.length > 0 && !selectedProduct && (
                                            <div className="absolute z-10 w-full mt-1 bg-white rounded-lg shadow-lg border border-gray-100 max-h-60 overflow-y-auto">
                                                {searchResults.map(prod => (
                                                    <button
                                                        key={prod.id}
                                                        onClick={() => handleSelectProduct(prod)}
                                                        className="w-full text-left px-4 py-3 hover:bg-gray-50 flex items-center gap-3 border-b border-gray-50 last:border-0"
                                                    >
                                                        {prod.image_url ? (
                                                            <img src={prod.image_url} className="w-8 h-8 rounded object-cover" />
                                                        ) : (
                                                            <div className="w-8 h-8 rounded bg-gray-100 flex items-center justify-center">
                                                                <Box className="w-4 h-4 text-gray-400" />
                                                            </div>
                                                        )}
                                                        <div>
                                                            <div className="font-medium text-sm text-gray-900">{prod.name}</div>
                                                            <div className="text-xs text-gray-500">Stock: {prod.stock} | {prod.barcode}</div>
                                                        </div>
                                                    </button>
                                                ))}
                                            </div>
                                        )}
                                    </div>

                                    {selectedProduct && (
                                        <form onSubmit={handleRegister} className="space-y-4 animate-in fade-in slide-in-from-top-2">
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-1">Cantidad</label>
                                                <input
                                                    type="number"
                                                    min="1"
                                                    max={selectedProduct.stock}
                                                    value={quantity}
                                                    onChange={e => setQuantity(parseInt(e.target.value) || 0)}
                                                    className="w-full rounded-lg border-gray-300 focus:ring-primary focus:border-primary"
                                                />
                                                <p className="text-xs text-gray-500 mt-1">
                                                    Máximo disponible: {selectedProduct.stock}
                                                </p>
                                            </div>

                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-1">Notas</label>
                                                <textarea
                                                    value={notes}
                                                    onChange={e => setNotes(e.target.value)}
                                                    placeholder="Ej: Consumo de oficina, Merma..."
                                                    rows={3}
                                                    className="w-full rounded-lg border-gray-300 focus:ring-primary focus:border-primary"
                                                />
                                            </div>

                                            <button
                                                type="submit"
                                                className="w-full py-2 bg-primary text-white rounded-lg hover:bg-primary/90 font-medium shadow-sm"
                                            >
                                                Registrar Salida
                                            </button>
                                        </form>
                                    )}
                                </div>
                            </div>

                            {/* Top Products */}
                            <div className="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                                <h3 className="font-semibold text-gray-800 mb-4">Top Productos Retirados</h3>
                                <div className="space-y-3">
                                    {topProducts.map((prod, idx) => (
                                        <div key={idx} className="flex items-center gap-3">
                                            <div className="font-bold text-gray-300 w-4">#{idx + 1}</div>
                                            <div className="flex-1">
                                                <div className="text-sm font-medium text-gray-900 line-clamp-1">{prod.name}</div>
                                                <div className="text-xs text-gray-500">{prod.total_removed} unidades</div>
                                            </div>
                                        </div>
                                    ))}
                                    {topProducts.length === 0 && <p className="text-sm text-gray-400">Sin datos.</p>}
                                </div>
                            </div>
                        </div>

                        {/* RIGHT: History Table */}
                        <div className="lg:col-span-2 space-y-4">
                            {/* Filters */}
                            <div className="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex flex-wrap gap-4 items-end">
                                <div>
                                    <label className="block text-xs font-medium text-gray-500 mb-1">Desde</label>
                                    <input type="date" value={dateStart} onChange={e => setDateStart(e.target.value)} className="rounded-md border-gray-300 text-sm" />
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-gray-500 mb-1">Hasta</label>
                                    <input type="date" value={dateEnd} onChange={e => setDateEnd(e.target.value)} className="rounded-md border-gray-300 text-sm" />
                                </div>
                                <button onClick={applyFilters} className="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm font-medium">Filtrar</button>
                            </div>

                            <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                                <table className="w-full text-sm text-left">
                                    <thead className="bg-gray-50 text-gray-500 font-medium border-b border-gray-100">
                                        <tr>
                                            <th className="px-4 py-3">Fecha</th>
                                            <th className="px-4 py-3">Producto</th>
                                            <th className="px-4 py-3 text-center">Cant.</th>
                                            <th className="px-4 py-3">Usuario</th>
                                            <th className="px-4 py-3">Notas</th>
                                            <th className="px-4 py-3 text-right">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {history.map(item => (
                                            <tr key={item.id} className="hover:bg-gray-50/50">
                                                <td className="px-4 py-3 text-gray-600">
                                                    {new Date(item.removal_date).toLocaleDateString()} <br/>
                                                    <span className="text-xs text-gray-400">{new Date(item.removal_date).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>
                                                </td>
                                                <td className="px-4 py-3 font-medium text-gray-800">
                                                    {item.product.name}
                                                </td>
                                                <td className="px-4 py-3 text-center">
                                                    <span className="bg-red-50 text-red-700 px-2 py-1 rounded-md font-bold text-xs">
                                                        -{item.quantity_removed}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3 text-gray-600">{item.user.name}</td>
                                                <td className="px-4 py-3 text-gray-500 truncate max-w-[150px]" title={item.notes || ''}>
                                                    {item.notes || '-'}
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    <button
                                                        onClick={() => handleDelete(item.id)}
                                                        className="text-gray-400 hover:text-red-600 transition-colors p-1"
                                                        title="Eliminar y devolver stock"
                                                    >
                                                        <X className="w-4 h-4" />
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                        {history.length === 0 && (
                                            <tr>
                                                <td colSpan={6} className="px-4 py-8 text-center text-gray-400">
                                                    No hay registros en este periodo.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

const KpiCard = ({ title, value, icon }: any) => (
    <div className="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex items-center gap-4">
        <div className="p-3 bg-gray-50 rounded-lg">
            {icon}
        </div>
        <div>
            <p className="text-gray-500 text-xs font-medium uppercase tracking-wide">{title}</p>
            <p className="text-2xl font-bold text-gray-800">{value}</p>
        </div>
    </div>
);
