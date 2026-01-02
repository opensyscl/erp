import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage, Link } from '@inertiajs/react';
import { useTenantRoute } from '@/Hooks/useTenantRoute';
import { useState, useEffect, useRef } from 'react';
import {
    AlertTriangle,
    Calendar,
    Search,
    RotateCcw,
    TrendingDown,
    Package,
    AlertCircle,
    XCircle,
    DollarSign,
    Box
} from 'lucide-react';
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js';
import { Doughnut } from 'react-chartjs-2';
import { toast } from 'sonner';

ChartJS.register(ArcElement, Tooltip, Legend);

interface DecreaseRecord {
    id: number;
    product: { id: number; name: string; barcode: string; cost: number; stock: number; supplier?: { name: string } };
    supplier: { id: number; name: string } | null;
    quantity: number;
    type: 'vencimiento' | 'daño' | 'devolucion';
    cost_per_unit: number;
    total_cost_loss: number;
    reason_notes: string | null;
    created_at: string;
    recorded_by: string;
}

interface KPIProps {
    total_cost_loss: number;
    total_margin_loss: number;
    total_potential_loss: number;
    top_supplier: { name: string; total_loss: number } | null;
    total_quantity: number;
    top_product: { name: string; total_loss: number } | null;
    avg_cost_per_unit: number;
    loss_vs_margin_pct: number;
    sales_margin_base: number;
    loss_by_expiration: number;
    loss_by_damage: number;
    loss_by_return: number;
    total_records: number;
}

interface Props {
    kpis: KPIProps;
    records: DecreaseRecord[];
    selectedMonth: string;
    monthOptions: { value: string; label: string }[];
    lossByType: Record<string, number>;
}

export default function Index({ kpis, records, selectedMonth, monthOptions, lossByType }: Props) {
    const tRoute = useTenantRoute();
    const [searchTerm, setSearchTerm] = useState('');
    const [searchResults, setSearchResults] = useState<any[]>([]);
    const [selectedProduct, setSelectedProduct] = useState<any | null>(null);
    const [quantity, setQuantity] = useState('');
    const [reason, setReason] = useState('vencimiento');
    const [notes, setNotes] = useState('');
    const [isReentering, setIsReentering] = useState(false);

    // Chart Data
    const chartData = {
        labels: ['Vencimiento', 'Daño', 'Devolución'],
        datasets: [
            {
                data: [
                    lossByType['vencimiento'] || 0,
                    lossByType['daño'] || 0,
                    lossByType['devolucion'] || 0,
                ],
                backgroundColor: [
                    'rgba(239, 68, 68, 0.7)', // Red - Vencimiento
                    'rgba(245, 158, 11, 0.7)', // Amber - Daño
                    'rgba(59, 130, 246, 0.7)', // Blue - Devolución
                ],
                borderColor: [
                    'rgba(239, 68, 68, 1)',
                    'rgba(245, 158, 11, 1)',
                    'rgba(59, 130, 246, 1)',
                ],
                borderWidth: 1,
            },
        ],
    };

    const handleMonthChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        router.get(tRoute('decrease.index'), { month: e.target.value }, { preserveState: true });
    };

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(amount);
    };

    // Product Search
    useEffect(() => {
        if (searchTerm.length < 2) {
            setSearchResults([]);
            return;
        }
        const timer = setTimeout(async () => {
            try {
                // Reuse Offers search for simplicity or create a specific endpoint if needed.
                // Assuming offers.search returns generic product structure
                const response = await fetch(tRoute('offers.search') + `?query=${encodeURIComponent(searchTerm)}`);
                const data = await response.json();
                setSearchResults(data);
            } catch (error) {
                console.error("Search error", error);
            }
        }, 300);
        return () => clearTimeout(timer);
    }, [searchTerm]);

    const handleSelectProduct = (product: any) => {
        setSelectedProduct(product);
        setSearchTerm('');
        setSearchResults([]);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedProduct || !quantity) return;

        // Basic frontend validation for stock
        if (parseInt(quantity) > selectedProduct.stock) {
            if(!confirm(`ADVERTENCIA: La cantidad ingresada (${quantity}) supera el stock actual (${selectedProduct.stock}). ¿Desea continuar y dejar el stock en negativo?`)) {
                return;
            }
        }

        router.post(tRoute('decrease.store'), {
            product_id: selectedProduct.id,
            quantity: quantity,
            type: reason,
            notes: notes,
            // Supplier is usually tied to product, we'll let backend handle it or send if available
            supplier_id: selectedProduct.supplier_id
        }, {
            onSuccess: () => {
                toast.success('Merma registrada correctamente');
                setSelectedProduct(null);
                setQuantity('');
                setNotes('');
            },
            onError: () => toast.error('Error al registrar merma')
        });
    };

    const handleReenter = (recordId: number) => {
        if (confirm('¿Está seguro de reingresar este stock? Se eliminará el registro de pérdida y se restaurará el inventario.')) {
            setIsReentering(true);
            router.delete(tRoute('decrease.destroy', { decrease: recordId }), {
                onSuccess: () => {
                    toast.success('Stock reingresado correctamente');
                    setIsReentering(false);
                },
                onError: () => {
                    toast.error('Error al reingresar stock');
                    setIsReentering(false);
                }
            });
        }
    };

    // KPICard Component
    const KPICard = ({ title, value, subtitle, colorClass = "text-gray-900", subColorClass = "text-gray-500", highlight = false }: any) => (
        <div className={`p-4 rounded-xl shadow-sm border ${highlight ? 'bg-indigo-50 border-indigo-100' : 'bg-white border-gray-100'}`}>
            <h3 className="text-xs font-semibold text-gray-500 mb-1 uppercase tracking-vide">{title}</h3>
            <div className={`text-2xl font-bold mb-1 ${colorClass}`}>{value}</div>
            <p className={`text-xs ${subColorClass}`}>{subtitle}</p>
        </div>
    );

    return (
        <AuthenticatedLayout header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Gestión de Mermas y Devoluciones</h2>}>
            <Head title="Mermas" />

            <div className="py-8 bg-gray-50 min-h-screen">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                    {/* Header with Month Selector */}
                    <div className="flex justify-between items-center mb-6">
                        <h1 className="text-2xl font-bold text-gray-800">Panel de Control</h1>
                        <div className="flex items-center gap-2 bg-white px-3 py-2 rounded-lg border shadow-sm">
                            <Calendar className="w-4 h-4 text-gray-500" />
                            <span className="text-sm font-medium text-gray-600">Ver reporte de:</span>
                            <select
                                value={selectedMonth}
                                onChange={handleMonthChange}
                                className="border-none text-sm font-semibold text-gray-900 focus:ring-0 cursor-pointer bg-transparent py-0"
                            >
                                {monthOptions.map(opt => (
                                    <option key={opt.value} value={opt.value}>{opt.label}</option>
                                ))}
                            </select>
                        </div>
                    </div>

                    {/* KPI Grid (4x3) */}
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                        {/* Row 1 */}
                        <KPICard
                            title="1. Pérdida por Costo (Bruta)"
                            value={formatCurrency(kpis.total_cost_loss)}
                            subtitle="Costo directo de los productos mermados."
                            colorClass="text-red-600"
                        />
                        <KPICard
                            title="2. Pérdida Potencial (Margen)"
                            value={formatCurrency(kpis.total_margin_loss)}
                            subtitle="Margen que se dejó de ganar por la merma."
                            colorClass="text-orange-500"
                        />
                        <KPICard
                            title="3. Pérdida Total (Pot. Venta)"
                            value={formatCurrency(kpis.total_potential_loss)}
                            subtitle="Costo + Margen (Impacto Total en P&L)."
                            colorClass="text-indigo-600"
                            highlight={true}
                        />
                        <KPICard
                            title="4. Proveedor con Más Pérdida"
                            value={formatCurrency(kpis.top_supplier?.total_loss || 0)}
                            subtitle={kpis.top_supplier ? `Proveedor: ${kpis.top_supplier.name}` : 'N/A'}
                            colorClass="text-red-600"
                        />

                        {/* Row 2 */}
                        <KPICard
                            title="5. Unidades Mermadas Totales"
                            value={`${kpis.total_quantity} u.`}
                            subtitle="Total de unidades retiradas del stock."
                        />
                        <KPICard
                            title="6. Producto con Mayor Pérdida"
                            value={formatCurrency(kpis.top_product?.total_loss || 0)}
                            subtitle={kpis.top_product ? `Producto: ${kpis.top_product.name}` : 'N/A'}
                            colorClass="text-red-600"
                        />
                        <KPICard
                            title="7. Costo Promedio por Unidad"
                            value={formatCurrency(kpis.avg_cost_per_unit)}
                            subtitle="Costo unitario promedio de lo perdido."
                            colorClass="text-orange-500"
                        />
                        <KPICard
                            title="8. % Merma vs. Margen Bruto"
                            value={(
                                <span className={kpis.loss_vs_margin_pct > 10 ? 'text-red-600' : kpis.loss_vs_margin_pct > 5 ? 'text-orange-500' : 'text-green-600'}>
                                    {kpis.loss_vs_margin_pct.toFixed(2)}%
                                    <span className={`ml-2 text-[10px] px-1.5 py-0.5 rounded-full border ${kpis.loss_vs_margin_pct > 10 ? 'bg-red-50 border-red-200 text-red-700' : kpis.loss_vs_margin_pct > 5 ? 'bg-orange-50 border-orange-200 text-orange-700' : 'bg-green-50 border-green-200 text-green-700'}`}>
                                        {kpis.loss_vs_margin_pct > 10 ? 'CRÍTICO' : kpis.loss_vs_margin_pct > 5 ? 'ALTO' : 'NORMAL'}
                                    </span>
                                </span>
                            )}
                            subtitle={`% / Venta Mensual (Base: ${formatCurrency(kpis.sales_margin_base)})`}
                        />

                        {/* Row 3 */}
                        <KPICard
                            title="9. Merma por Vencimiento"
                            value={formatCurrency(kpis.loss_by_expiration)}
                            subtitle="Pérdida por productos caducados."
                            colorClass="text-red-600"
                        />
                        <KPICard
                            title="10. Merma por Daño"
                            value={formatCurrency(kpis.loss_by_damage)}
                            subtitle="Pérdida por rotura o deterioro."
                            colorClass="text-orange-500"
                        />
                        <KPICard
                            title="11. Merma por Devolución"
                            value={formatCurrency(kpis.loss_by_return)}
                            subtitle="Pérdida por ítems devueltos."
                            colorClass="text-green-600"
                        />
                        <KPICard
                            title="12. Total de Registros"
                            value={kpis.total_records}
                            subtitle="Número total de transacciones registradas."
                        />
                    </div>

                    {/* Form and Chart Section */}
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">

                        {/* Registration Form */}
                        <div className="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                            <h2 className="text-lg font-bold text-gray-800 mb-4 pb-2 border-b">Registrar Disminución de Stock</h2>

                            <form onSubmit={handleSubmit} className="space-y-4">
                                {/* Product Search */}
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Buscar Producto</label>
                                    <div className="relative">
                                        <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <Search className="h-4 w-4 text-gray-400" />
                                        </div>
                                        <input
                                            type="text"
                                            value={selectedProduct ? selectedProduct.name : searchTerm}
                                            onChange={(e) => { setSearchTerm(e.target.value); if(selectedProduct) setSelectedProduct(null); }}
                                            placeholder="Escriba para buscar por nombre o código..."
                                            className="pl-10 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        />
                                        {searchResults.length > 0 && !selectedProduct && (
                                            <div className="absolute z-10 w-full bg-white mt-1 border border-gray-200 rounded-md shadow-lg max-h-60 overflow-y-auto">
                                                {searchResults.map((product) => (
                                                    <div
                                                        key={product.id}
                                                        className="px-4 py-2 hover:bg-gray-50 cursor-pointer text-sm"
                                                        onClick={() => handleSelectProduct(product)}
                                                    >
                                                        <div className="font-medium text-gray-900">{product.name}</div>
                                                        <div className="text-gray-500 text-xs flex justify-between">
                                                            <span>Stock: {product.stock}</span>
                                                            <span>{formatCurrency(product.price)}</span>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                    {selectedProduct && (
                                        <div className="mt-2 text-sm bg-blue-50 text-blue-800 p-2 rounded-md border border-blue-100">
                                            <div><strong>Producto:</strong> {selectedProduct.name}</div>
                                            <div><strong>Stock Actual:</strong> {selectedProduct.stock}</div>
                                            <div><strong>Costo Ref:</strong> {formatCurrency(selectedProduct.cost)}</div>
                                        </div>
                                    )}
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Cantidad</label>
                                        <input
                                            type="number"
                                            min="1"
                                            value={quantity}
                                            onChange={(e) => setQuantity(e.target.value)}
                                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                            disabled={!selectedProduct}
                                            required
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Razón</label>
                                        <select
                                            value={reason}
                                            onChange={(e) => setReason(e.target.value)}
                                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                            disabled={!selectedProduct}
                                        >
                                            <option value="vencimiento">Vencimiento</option>
                                            <option value="daño">Daño</option>
                                            <option value="devolucion">Devolución a Prov.</option>
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Notas (Opcional)</label>
                                    <textarea
                                        value={notes}
                                        onChange={(e) => setNotes(e.target.value)}
                                        rows={2}
                                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        disabled={!selectedProduct}
                                    />
                                </div>

                                <button
                                    type="submit"
                                    disabled={!selectedProduct || !quantity}
                                    className="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-slate-800 hover:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                >
                                    Registrar Merma y Descontar Stock
                                </button>
                            </form>
                        </div>

                        {/* Chart */}
                        <div className="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex flex-col">
                            <h2 className="text-lg font-bold text-gray-800 mb-4 pb-2 border-b">Distribución de Pérdida por Razón</h2>
                            <div className="flex-1 min-h-[300px] flex items-center justify-center relative">
                                {Object.values(lossByType).some(v => v > 0) ? (
                                    <div className="w-64 h-64">
                                        <Doughnut data={chartData} options={{ responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }} />
                                    </div>
                                ) : (
                                    <div className="text-center text-gray-400">
                                        <TrendingDown className="w-12 h-12 mx-auto mb-2 opacity-20" />
                                        <p>No hay datos registrados este mes</p>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Table */}
                    <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div className="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                            <h2 className="text-lg font-bold text-gray-800">Registros Recientes</h2>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Producto</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Proveedor</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cant.</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pérdida ($)</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notas</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                        <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acción</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200">
                                    {records.length > 0 ? (
                                        records.map((record) => (
                                            <tr key={record.id} className="hover:bg-gray-50 transition-colors">
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className="text-sm font-medium text-gray-900">{record.product?.name || 'Unknown'}</div>
                                                    <div className="text-xs text-gray-500">{record.product?.barcode}</div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {record.supplier?.name || '-'}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm font-bold text-red-600">
                                                    {record.quantity}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                                        ${record.type === 'vencimiento' ? 'bg-red-100 text-red-800' :
                                                          record.type === 'daño' ? 'bg-orange-100 text-orange-800' :
                                                          'bg-blue-100 text-blue-800'}`}>
                                                        {record.type.charAt(0).toUpperCase() + record.type.slice(1)}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {formatCurrency(record.total_cost_loss)}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 max-w-xs truncate" title={record.reason_notes || ''}>
                                                    {record.reason_notes || '-'}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {new Date(record.created_at).toLocaleDateString()}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                    <button
                                                        onClick={() => handleReenter(record.id)}
                                                        disabled={isReentering}
                                                        className="text-indigo-600 hover:text-indigo-900 flex items-center gap-1 justify-end ml-auto"
                                                        title="Reingresar al stock (Deshacer merma)"
                                                    >
                                                        <RotateCcw className="w-4 h-4" />
                                                        Reingreso
                                                    </button>
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={8} className="px-6 py-8 text-center text-gray-500">
                                                No hay registros de mermas para este mes.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
