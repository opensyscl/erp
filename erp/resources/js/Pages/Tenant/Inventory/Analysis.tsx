import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useTenantRoute } from '@/Hooks/useTenantRoute';
import { useState } from 'react';
import {
    Package,
    AlertTriangle,
    AlertCircle,
    CheckCircle,
    ChevronDown,
    ChevronUp,
    Filter,
    FileDown,
    Mail
} from 'lucide-react';
import { NavTab } from '@/components/SectionNav';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

interface Product {
    id: number;
    name: string;
    sku: string | null;
    price: number;
    cost: number;
    stock: number;
    min_stock: number;
    image: string | null;
    supplier?: { id: number; name: string } | null;
    sales_since_last_purchase?: number | null;
    daily_sales_avg?: number | null;
    days_remaining?: number | null;
    last_purchase_date?: string | null;
}

interface Supplier {
    id: number;
    name: string;
}

interface StockAnalysis {
    total_products: number;
    total_value: number;
    total_cost: number;
    low_stock: number;
    low_stock_threshold_40: number;
    critical_stock: number;
    out_of_stock: number;
    negative_stock: number;
    without_image: number;
    without_supplier: number;
    archived: number;
}

interface Props {
    stockAnalysis: StockAnalysis;
    criticalProducts: Product[];
    suppliers: Supplier[];
}

export default function Analysis({
    stockAnalysis,
    criticalProducts,
    suppliers = [],
}: Props) {
    const tRoute = useTenantRoute();
    const [selectedSupplier, setSelectedSupplier] = useState<string>('all');
    const [sortField, setSortField] = useState<'stock' | 'sales' | 'avg' | 'days'>('stock');
    const [sortDir, setSortDir] = useState<'asc' | 'desc'>('asc');

    const formatNumber = (num: number) => {
        return new Intl.NumberFormat('es-MX').format(num);
    };

    // Navigation tabs for inventory section
    const inventoryTabs: NavTab[] = [
        { label: 'Inventario', href: tRoute('inventory.index'), active: false },
        { label: 'Análisis de Inventario', href: tRoute('inventory.analysis'), active: true },
        { label: 'Conteo Físico', href: '#', disabled: true },
    ];

    // Filter and sort products
    const filteredProducts = criticalProducts
        .filter(p => selectedSupplier === 'all' || p.supplier?.id.toString() === selectedSupplier)
        .sort((a, b) => {
            let aVal = 0, bVal = 0;
            switch (sortField) {
                case 'stock': aVal = a.stock; bVal = b.stock; break;
                case 'sales': aVal = a.sales_since_last_purchase ?? 0; bVal = b.sales_since_last_purchase ?? 0; break;
                case 'avg': aVal = a.daily_sales_avg ?? 0; bVal = b.daily_sales_avg ?? 0; break;
                case 'days': aVal = a.days_remaining ?? 9999; bVal = b.days_remaining ?? 9999; break;
            }
            return sortDir === 'asc' ? aVal - bVal : bVal - aVal;
        });

    const handleSort = (field: typeof sortField) => {
        if (sortField === field) {
            setSortDir(sortDir === 'asc' ? 'desc' : 'asc');
        } else {
            setSortField(field);
            setSortDir('asc');
        }
    };

    const SortIcon = ({ field }: { field: typeof sortField }) => {
        if (sortField !== field) return <ChevronDown className="w-3 h-3 opacity-30" />;
        return sortDir === 'asc'
            ? <ChevronUp className="w-3 h-3 text-primary" />
            : <ChevronDown className="w-3 h-3 text-primary" />;
    };

    const getAlertIcon = (product: Product) => {
        const days = product.days_remaining;
        if (days === null || days === undefined) {
            return <CheckCircle className="w-5 h-5 text-success" />;
        }
        if (days <= 7) {
            return <AlertTriangle className="w-5 h-5 text-danger" />;
        }
        if (days <= 30) {
            return <AlertTriangle className="w-5 h-5 text-warning" />;
        }
        return <CheckCircle className="w-5 h-5 text-success" />;
    };

    const handleApplyFilters = () => {
        router.reload({ only: ['criticalProducts'] });
    };

    return (
        <AuthenticatedLayout sectionTabs={inventoryTabs}>
            <Head title="Análisis de Inventario" />

            <div className="py-6 px-4 sm:px-6 lg:px-8 max-w-[1600px] mx-auto">
                {/* Header */}
                <div className="mb-8">
                    <h1 className="text-2xl font-bold text-gray-900">Análisis de Inventario</h1>
                    <p className="text-gray-500 mt-1">Vista general del estado y salud de tu inventario</p>
                </div>

                {/* Main Stats Grid */}
                <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-8">
                    {/* Stock Bajo ≤40 */}
                    <div className="bg-white rounded-2xl border-2 border-warning/30 p-5 hover:shadow-lg transition-shadow">
                        <div className="flex items-center justify-between mb-3">
                            <span className="text-xs font-medium text-gray-500 uppercase tracking-wide">
                                Productos con Stock<br />Bajo (≤ 40)
                            </span>
                            <div className="w-8 h-8 rounded-lg bg-warning/10 flex items-center justify-center">
                                <AlertTriangle className="w-4 h-4 text-warning" />
                            </div>
                        </div>
                        <p className="text-3xl font-bold text-warning">
                            {formatNumber(stockAnalysis.low_stock_threshold_40)}
                        </p>
                    </div>

                    {/* Stock Crítico <10 */}
                    <div className="bg-white rounded-2xl border-2 border-danger/30 p-5 hover:shadow-lg transition-shadow">
                        <div className="flex items-center justify-between mb-3">
                            <span className="text-xs font-medium text-gray-500 uppercase tracking-wide">
                                Productos con Stock<br />Crítico (&lt; 10)
                            </span>
                            <div className="w-8 h-8 rounded-lg bg-danger/10 flex items-center justify-center">
                                <AlertCircle className="w-4 h-4 text-danger" />
                            </div>
                        </div>
                        <p className="text-3xl font-bold text-danger">
                            {formatNumber(stockAnalysis.critical_stock)}
                        </p>
                    </div>

                    {/* Fecha última factura - Placeholder */}
                    <div className="bg-white rounded-2xl border border-gray-200 p-5">
                        <div className="flex items-center justify-between mb-3">
                            <span className="text-xs font-medium text-gray-500 uppercase tracking-wide">
                                Fecha Última Factura<br />(Prov.)
                            </span>
                        </div>
                        <p className="text-3xl font-bold text-gray-400">N/A</p>
                    </div>

                    {/* Promedio venta diario - Placeholder */}
                    <div className="bg-white rounded-2xl border border-gray-200 p-5">
                        <div className="flex items-center justify-between mb-3">
                            <span className="text-xs font-medium text-gray-500 uppercase tracking-wide">
                                Promedio Venta Diario<br />(Prov.)
                            </span>
                        </div>
                        <p className="text-3xl font-bold text-gray-400">N/A</p>
                    </div>

                    {/* Ventas desde última compra - Placeholder */}
                    <div className="bg-white rounded-2xl border border-gray-200 p-5">
                        <div className="flex items-center justify-between mb-3">
                            <span className="text-xs font-medium text-gray-500 uppercase tracking-wide">
                                Ventas Desde Última<br />Compra (Prov.)
                            </span>
                        </div>
                        <p className="text-3xl font-bold text-gray-400">N/A</p>
                    </div>
                </div>

                {/* Filters Row */}
                <div className="bg-white rounded-2xl border border-gray-100 p-4 mb-6">
                    <div className="flex flex-wrap items-center gap-4">
                        <div className="flex items-center gap-2">
                            <span className="text-sm font-medium text-gray-700">Proveedor:</span>
                            <Select value={selectedSupplier} onValueChange={setSelectedSupplier}>
                                <SelectTrigger className="w-[220px] rounded-xl">
                                    <SelectValue placeholder="Todos los Proveedores" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todos los Proveedores</SelectItem>
                                    {suppliers.map(sup => (
                                        <SelectItem key={sup.id} value={sup.id.toString()}>
                                            {sup.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <button
                            onClick={handleApplyFilters}
                            className="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-xl hover:bg-primary/90 transition-colors"
                        >
                            <Filter className="w-4 h-4" />
                            Aplicar Filtros
                        </button>

                        <button
                            className="inline-flex items-center gap-2 px-4 py-2 bg-gray-800 text-white text-sm font-medium rounded-xl hover:bg-gray-700 transition-colors"
                        >
                            <Mail className="w-4 h-4" />
                            Enviar Informe (Crítico)
                        </button>

                        <button
                            className="inline-flex items-center gap-2 px-4 py-2 bg-gray-800 text-white text-sm font-medium rounded-xl hover:bg-gray-700 transition-colors"
                        >
                            <FileDown className="w-4 h-4" />
                            Descargar Reporte (Bajo y Crítico)
                        </button>
                    </div>
                </div>

                {/* Info Banner */}
                <div className="bg-success/10 border border-success/20 rounded-xl px-4 py-3 mb-6">
                    <p className="text-sm text-center text-gray-700">
                        Analizando: <strong>{formatNumber(filteredProducts.length)}</strong> productos.
                        Las métricas de la tabla se calculan desde la <strong>**última compra de cada producto**</strong>.
                    </p>
                </div>

                {/* Table Section */}
                <div className="bg-white rounded-2xl border border-gray-100 overflow-hidden">
                    <div className="p-5 border-b border-gray-100">
                        <h3 className="text-lg font-semibold text-gray-900">Detalle de Inventario y Pedidos</h3>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead>
                                <tr className="bg-gray-50 border-b border-gray-100">
                                    <th className="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                        ID
                                    </th>
                                    <th className="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                        Producto
                                    </th>
                                    <th className="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                        Proveedor
                                    </th>
                                    <th
                                        className="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:text-primary"
                                        onClick={() => handleSort('stock')}
                                    >
                                        <div className="flex items-center gap-1">
                                            Stock Actual
                                            <SortIcon field="stock" />
                                        </div>
                                    </th>
                                    <th
                                        className="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:text-primary"
                                        onClick={() => handleSort('sales')}
                                    >
                                        <div className="flex items-center gap-1">
                                            Ventas Desde Última Compra
                                            <SortIcon field="sales" />
                                        </div>
                                    </th>
                                    <th
                                        className="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:text-primary"
                                        onClick={() => handleSort('avg')}
                                    >
                                        <div className="flex items-center gap-1">
                                            Promedio de Ventas Diario
                                            <SortIcon field="avg" />
                                        </div>
                                    </th>
                                    <th
                                        className="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider cursor-pointer hover:text-primary"
                                        onClick={() => handleSort('days')}
                                    >
                                        <div className="flex items-center gap-1">
                                            Días Restantes
                                            <SortIcon field="days" />
                                        </div>
                                    </th>
                                    <th className="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                        Alerta
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50">
                                {filteredProducts.length === 0 ? (
                                    <tr>
                                        <td colSpan={8} className="px-4 py-12 text-center">
                                            <Package className="w-12 h-12 mx-auto mb-3 text-gray-300" />
                                            <p className="text-gray-500">No hay productos que mostrar</p>
                                        </td>
                                    </tr>
                                ) : (
                                    filteredProducts.map((product) => (
                                        <tr key={product.id} className="hover:bg-gray-50 transition-colors">
                                            <td className="px-4 py-3 text-sm text-gray-900 font-medium">
                                                {product.id}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-gray-900 max-w-[250px]">
                                                <span className="truncate block">{product.name}</span>
                                            </td>
                                            <td className="px-4 py-3 text-sm text-gray-600">
                                                {product.supplier?.name || '-'}
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className={`text-sm font-bold ${
                                                    product.stock < 10 ? 'text-danger' :
                                                    product.stock <= 40 ? 'text-warning' :
                                                    'text-gray-900'
                                                }`}>
                                                    {formatNumber(product.stock)}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-sm text-gray-600">
                                                {product.sales_since_last_purchase != null
                                                    ? formatNumber(product.sales_since_last_purchase)
                                                    : '0.00'}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-gray-600">
                                                {product.daily_sales_avg != null
                                                    ? product.daily_sales_avg.toFixed(2)
                                                    : '0.00'}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-gray-600">
                                                {product.days_remaining != null
                                                    ? `${formatNumber(product.days_remaining)} días`
                                                    : 'N/A'}
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                {getAlertIcon(product)}
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
