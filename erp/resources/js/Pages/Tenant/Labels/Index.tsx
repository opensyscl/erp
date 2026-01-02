import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage } from '@inertiajs/react';
import { useTenantRoute } from '@/Hooks/useTenantRoute';
import { useState, useMemo, useCallback } from 'react';
import { toast } from 'sonner';
import {
    Package,
    Barcode,
    Image,
    AlertCircle,
    DollarSign,
    Search,
    Printer,
    CheckSquare,
    Square
} from 'lucide-react';

interface Product {
    id: number;
    name: string;
    barcode: string | null;
    price: number;
    stock: number;
    image: string | null;
    category_id: number | null;
    supplier_id: number | null;
}

interface Category {
    id: number;
    name: string;
}

interface Supplier {
    id: number;
    name: string;
}

interface KPIs {
    total_products: number;
    no_barcode: number;
    with_image_percent: number;
    out_of_stock: number;
    avg_price: number;
}

interface Props {
    products: Product[];
    kpis: KPIs;
    categories: Category[];
    suppliers: Supplier[];
}

export default function Index({ products, kpis, categories, suppliers }: Props) {
    const tRoute = useTenantRoute();
    const { tenant } = usePage().props as any;

    // Filters
    const [searchQuery, setSearchQuery] = useState('');
    const [categoryFilter, setCategoryFilter] = useState<string>('all');
    const [supplierFilter, setSupplierFilter] = useState<string>('all');

    // Selection
    const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());

    const formatCurrency = (amount: number) => {
        return '$' + Math.round(amount).toLocaleString('es-CL');
    };

    // Filtered products
    const filteredProducts = useMemo(() => {
        return products.filter(product => {
            // Category filter
            if (categoryFilter !== 'all' && product.category_id !== parseInt(categoryFilter)) {
                return false;
            }
            // Supplier filter
            if (supplierFilter !== 'all') {
                if (supplierFilter === '0') {
                    if (product.supplier_id !== null && product.supplier_id !== 0) return false;
                } else if (product.supplier_id !== parseInt(supplierFilter)) {
                    return false;
                }
            }
            // Search filter
            if (searchQuery) {
                const query = searchQuery.toLowerCase();
                return (
                    product.name.toLowerCase().includes(query) ||
                    (product.barcode && product.barcode.toLowerCase().includes(query))
                );
            }
            return true;
        });
    }, [products, searchQuery, categoryFilter, supplierFilter]);

    // Limited display (30 if no filters, all if filtered)
    const displayProducts = useMemo(() => {
        const hasFilters = searchQuery || categoryFilter !== 'all' || supplierFilter !== 'all';
        return hasFilters ? filteredProducts : filteredProducts.slice(0, 30);
    }, [filteredProducts, searchQuery, categoryFilter, supplierFilter]);

    const toggleSelect = useCallback((id: number) => {
        setSelectedIds(prev => {
            const next = new Set(prev);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }
            return next;
        });
    }, []);

    const toggleSelectAll = useCallback(() => {
        if (selectedIds.size === displayProducts.length) {
            setSelectedIds(new Set());
        } else {
            setSelectedIds(new Set(displayProducts.map(p => p.id)));
        }
    }, [displayProducts, selectedIds]);

    const printLabel = useCallback((product: Product) => {
        const priceFormatted = Math.round(product.price).toLocaleString('es-CL');
        const storeName = tenant?.name || 'Mi Tienda';
        const storeLogo = tenant?.logo || '';
        const url = `/labels/print?name=${encodeURIComponent(product.name)}&barcode=${encodeURIComponent(product.barcode || 'N/A')}&price=${encodeURIComponent(priceFormatted)}&store=${encodeURIComponent(storeName)}&logo=${encodeURIComponent(storeLogo)}`;
        window.open(url, '_blank', 'width=400,height=250');
    }, [tenant]);

    const printSelected = useCallback(() => {
        if (selectedIds.size === 0) {
            toast.error('Selecciona al menos un producto');
            return;
        }

        const selectedProducts = displayProducts.filter(p => selectedIds.has(p.id));
        let delay = 0;

        selectedProducts.forEach((product, index) => {
            setTimeout(() => {
                printLabel(product);
                if (index === selectedProducts.length - 1) {
                    toast.success(`Imprimiendo ${selectedProducts.length} etiquetas`);
                }
            }, delay);
            delay += 300;
        });

        setSelectedIds(new Set());
    }, [selectedIds, displayProducts, printLabel]);

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
            <Head title="Centro de Etiquetas" />

            <div className="py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">🖨️ Centro de Etiquetas</h1>
                        <p className="text-sm text-gray-500">Imprime etiquetas de precios para tus productos</p>
                    </div>
                    <button
                        onClick={printSelected}
                        disabled={selectedIds.size === 0}
                        className="px-4 py-2 bg-primary text-white rounded-lg font-medium hover:bg-primary/90 disabled:opacity-50 flex items-center gap-2"
                    >
                        <Printer className="w-4 h-4" />
                        Imprimir Seleccionadas ({selectedIds.size})
                    </button>
                </div>

                {/* KPIs */}
                <div className="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                    <KpiCard title="Productos Cargados" value={kpis.total_products} icon={Package} color="border-green-500" />
                    <KpiCard title="Sin Cód. Barra" value={kpis.no_barcode} icon={Barcode} color="border-orange-500" />
                    <KpiCard title="Con Imagen" value={`${kpis.with_image_percent}%`} icon={Image} color="border-purple-500" />
                    <KpiCard title="Sin Stock" value={kpis.out_of_stock} icon={AlertCircle} color="border-red-500" />
                    <KpiCard title="Precio Promedio" value={formatCurrency(kpis.avg_price)} icon={DollarSign} color="border-blue-500" />
                </div>

                {/* Table Card */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    {/* Filters */}
                    <div className="p-4 border-b border-gray-100">
                        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                            <h2 className="text-lg font-semibold">
                                Listado de Productos
                                <span className="text-sm font-normal text-gray-500 ml-2">
                                    ({displayProducts.length} de {filteredProducts.length})
                                </span>
                            </h2>
                            <div className="flex flex-wrap gap-2">
                                <select
                                    value={categoryFilter}
                                    onChange={(e) => setCategoryFilter(e.target.value)}
                                    className="px-3 py-1.5 border rounded-lg text-sm"
                                >
                                    <option value="all">Todas las Categorías</option>
                                    {categories.map(cat => (
                                        <option key={cat.id} value={cat.id}>{cat.name}</option>
                                    ))}
                                </select>
                                <select
                                    value={supplierFilter}
                                    onChange={(e) => setSupplierFilter(e.target.value)}
                                    className="px-3 py-1.5 border rounded-lg text-sm"
                                >
                                    <option value="all">Todos los Proveedores</option>
                                    <option value="0">Sin Proveedor</option>
                                    {suppliers.map(sup => (
                                        <option key={sup.id} value={sup.id}>{sup.name}</option>
                                    ))}
                                </select>
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                                    <input
                                        type="text"
                                        placeholder="Buscar..."
                                        value={searchQuery}
                                        onChange={(e) => setSearchQuery(e.target.value)}
                                        className="pl-9 pr-4 py-1.5 border rounded-lg text-sm w-48"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Table */}
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left">
                                        <button onClick={toggleSelectAll} className="hover:text-primary">
                                            {selectedIds.size === displayProducts.length && displayProducts.length > 0
                                                ? <CheckSquare className="w-4 h-4" />
                                                : <Square className="w-4 h-4" />
                                            }
                                        </button>
                                    </th>
                                    <th className="px-4 py-3 text-center font-medium text-gray-600">Imagen</th>
                                    <th className="px-4 py-3 text-left font-medium text-gray-600">ID</th>
                                    <th className="px-4 py-3 text-left font-medium text-gray-600">Código</th>
                                    <th className="px-4 py-3 text-left font-medium text-gray-600">Nombre</th>
                                    <th className="px-4 py-3 text-right font-medium text-gray-600">Precio</th>
                                    <th className="px-4 py-3 text-center font-medium text-gray-600">Stock</th>
                                    <th className="px-4 py-3 text-center font-medium text-gray-600">Acción</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {displayProducts.length === 0 ? (
                                    <tr>
                                        <td colSpan={8} className="px-4 py-12 text-center text-gray-500">
                                            No se encontraron productos
                                        </td>
                                    </tr>
                                ) : (
                                    displayProducts.map(product => (
                                        <tr key={product.id} className="hover:bg-gray-50">
                                            <td className="px-4 py-3">
                                                <button onClick={() => toggleSelect(product.id)} className="hover:text-primary">
                                                    {selectedIds.has(product.id)
                                                        ? <CheckSquare className="w-4 h-4 text-primary" />
                                                        : <Square className="w-4 h-4" />
                                                    }
                                                </button>
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                {product.image ? (
                                                    <img
                                                        src={product.image}
                                                        alt={product.name}
                                                        className="w-10 h-10 object-cover rounded mx-auto"
                                                        onError={(e) => {
                                                            (e.target as HTMLImageElement).style.display = 'none';
                                                        }}
                                                    />
                                                ) : (
                                                    <div className="w-10 h-10 bg-gray-100 rounded mx-auto flex items-center justify-center text-xs text-gray-400">
                                                        N/A
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-gray-500">{product.id}</td>
                                            <td className="px-4 py-3 font-mono text-xs">{product.barcode || '-'}</td>
                                            <td className="px-4 py-3 font-medium">{product.name}</td>
                                            <td className="px-4 py-3 text-right font-medium">{formatCurrency(product.price)}</td>
                                            <td className={`px-4 py-3 text-center font-semibold ${product.stock > 0 ? 'text-green-600' : 'text-red-600'}`}>
                                                {product.stock}
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                <button
                                                    onClick={() => printLabel(product)}
                                                    className="px-3 py-1 bg-primary text-white text-xs rounded hover:bg-primary/90"
                                                >
                                                    Imprimir
                                                </button>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
