import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useTenantRoute } from '@/Hooks/useTenantRoute';
import { useState, useMemo } from 'react';
import {
    TrendingUp,
    DollarSign,
    Calendar,
    BarChart3,
    Printer,
    ChevronDown,
    Filter,
    RefreshCw
} from 'lucide-react';

interface Props {
    kpis: {
        daily_sales: number;
        daily_average: number;
        accumulated_sales: number;
        monthly_projection: number;
        daily_sale_date: string;
    };
    salesData: Array<{
        id: number;
        receipt_number: number;
        total: number;
        paid: number;
        change: number;
        payment_method: string;
        status: string;
        created_at: string;
        sale_date: string;
    }>;
    chartData: Record<string, number>;
    filters: {
        month: string;
        start_date: string | null;
        end_date: string | null;
        is_custom_range: boolean;
    };
    monthOptions: Array<{ value: string; label: string }>;
}

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('es-CL', {
        style: 'currency',
        currency: 'CLP',
        minimumFractionDigits: 0,
    }).format(amount);
};

const formatDate = (dateStr: string) => {
    const date = new Date(dateStr);
    return date.toLocaleDateString('es-CL', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getKpiColor = (value: number, thresholds: { low: number; high: number }) => {
    if (value < thresholds.low) return 'text-red-600 bg-red-50 border-red-200';
    if (value >= thresholds.high) return 'text-green-600 bg-green-50 border-green-200';
    return 'text-amber-600 bg-amber-50 border-amber-200';
};

export default function Index({ kpis, salesData, chartData, filters, monthOptions }: Props) {
    const tRoute = useTenantRoute();

    // Local filter state
    const [selectedMonth, setSelectedMonth] = useState(filters.month);
    const [startDate, setStartDate] = useState(filters.start_date || '');
    const [endDate, setEndDate] = useState(filters.end_date || '');
    const [itemsPerPage, setItemsPerPage] = useState(25);
    const [sortColumn, setSortColumn] = useState<string>('created_at');
    const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('desc');

    // Apply month filter
    const handleMonthChange = (month: string) => {
        setSelectedMonth(month);
        router.get(tRoute('sales.index'), { month }, { preserveState: true });
    };

    // Apply custom date range filter
    const handleDateRangeFilter = () => {
        if (startDate && endDate) {
            router.get(tRoute('sales.index'), { start_date: startDate, end_date: endDate }, { preserveState: true });
        }
    };

    // Reset filters
    const handleResetFilters = () => {
        router.get(tRoute('sales.index'), {}, { preserveState: false });
    };

    // Sorting
    const sortedData = useMemo(() => {
        const sorted = [...salesData].sort((a, b) => {
            let aVal: any = a[sortColumn as keyof typeof a];
            let bVal: any = b[sortColumn as keyof typeof b];

            if (sortColumn === 'created_at') {
                aVal = new Date(aVal).getTime();
                bVal = new Date(bVal).getTime();
            } else if (['total', 'paid', 'change', 'receipt_number', 'id'].includes(sortColumn)) {
                aVal = Number(aVal);
                bVal = Number(bVal);
            }

            if (aVal < bVal) return sortDirection === 'asc' ? -1 : 1;
            if (aVal > bVal) return sortDirection === 'asc' ? 1 : -1;
            return 0;
        });

        return itemsPerPage === -1 ? sorted : sorted.slice(0, itemsPerPage);
    }, [salesData, sortColumn, sortDirection, itemsPerPage]);

    const handleSort = (column: string) => {
        if (sortColumn === column) {
            setSortDirection(sortDirection === 'asc' ? 'desc' : 'asc');
        } else {
            setSortColumn(column);
            setSortDirection('desc');
        }
    };

    // Chart calculation (simple bar visualization)
    const chartDays = Object.keys(chartData);
    const chartValues = Object.values(chartData);
    const maxValue = Math.max(...chartValues, 1);
    const avgLine = kpis.daily_average;

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    📊 Reporte de Ventas
                </h2>
            }
        >
            <Head title="Reporte de Ventas" />

            <div className="py-8">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                    {/* Filters */}
                    <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                        <div className="flex flex-wrap items-center gap-4">
                            {/* Month Selector */}
                            <div className="flex items-center gap-2">
                                <label className="text-sm font-medium text-gray-700">Mes:</label>
                                <select
                                    value={selectedMonth}
                                    onChange={(e) => handleMonthChange(e.target.value)}
                                    className="rounded-md border-gray-300 text-sm"
                                >
                                    {monthOptions.map((opt) => (
                                        <option key={opt.value} value={opt.value}>{opt.label}</option>
                                    ))}
                                </select>
                            </div>

                            <div className="h-6 w-px bg-gray-200" />

                            {/* Date Range */}
                            <div className="flex items-center gap-2">
                                <label className="text-sm font-medium text-gray-700">Rango:</label>
                                <input
                                    type="date"
                                    value={startDate}
                                    onChange={(e) => setStartDate(e.target.value)}
                                    className="rounded-md border-gray-300 text-sm"
                                />
                                <span className="text-gray-500">a</span>
                                <input
                                    type="date"
                                    value={endDate}
                                    onChange={(e) => setEndDate(e.target.value)}
                                    className="rounded-md border-gray-300 text-sm"
                                />
                                <button
                                    onClick={handleDateRangeFilter}
                                    className="px-3 py-1.5 bg-primary text-white rounded-md text-sm hover:bg-primary/90 flex items-center gap-1"
                                >
                                    <Filter className="w-4 h-4" />
                                </button>
                            </div>

                            {filters.is_custom_range && (
                                <button
                                    onClick={handleResetFilters}
                                    className="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1"
                                >
                                    <RefreshCw className="w-4 h-4" />
                                    Reset
                                </button>
                            )}
                        </div>

                        {filters.is_custom_range && (
                            <p className="text-xs text-gray-500 mt-2">
                                Filtrando: <strong>{filters.start_date}</strong> a <strong>{filters.end_date}</strong>
                            </p>
                        )}
                    </div>

                    {/* KPI Cards */}
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        {/* Daily Sales */}
                        <div className={`rounded-xl p-6 border ${getKpiColor(kpis.daily_sales, { low: 200000, high: 300000 })}`}>
                            <div className="flex items-center justify-between">
                                <h3 className="text-sm font-medium opacity-80">Venta Diaria</h3>
                                <DollarSign className="w-5 h-5 opacity-60" />
                            </div>
                            <p className="text-2xl font-bold mt-2">{formatCurrency(kpis.daily_sales)}</p>
                            <p className="text-xs opacity-60 mt-1">{new Date(kpis.daily_sale_date).toLocaleDateString('es-CL')}</p>
                        </div>

                        {/* Daily Average */}
                        <div className={`rounded-xl p-6 border ${getKpiColor(kpis.daily_average, { low: 250000, high: 300000 })}`}>
                            <div className="flex items-center justify-between">
                                <h3 className="text-sm font-medium opacity-80">Promedio Diario</h3>
                                <BarChart3 className="w-5 h-5 opacity-60" />
                            </div>
                            <p className="text-2xl font-bold mt-2">{formatCurrency(kpis.daily_average)}</p>
                        </div>

                        {/* Accumulated Sales */}
                        <div className="rounded-xl p-6 border bg-blue-50 text-blue-700 border-blue-200">
                            <div className="flex items-center justify-between">
                                <h3 className="text-sm font-medium opacity-80">Ventas Acumuladas</h3>
                                <TrendingUp className="w-5 h-5 opacity-60" />
                            </div>
                            <p className="text-2xl font-bold mt-2">{formatCurrency(kpis.accumulated_sales)}</p>
                        </div>

                        {/* Monthly Projection */}
                        <div className="rounded-xl p-6 border bg-purple-50 text-purple-700 border-purple-200">
                            <div className="flex items-center justify-between">
                                <h3 className="text-sm font-medium opacity-80">Proyección Mensual</h3>
                                <Calendar className="w-5 h-5 opacity-60" />
                            </div>
                            <p className="text-2xl font-bold mt-2">
                                {kpis.monthly_projection > 0 ? formatCurrency(kpis.monthly_projection) : 'N/A'}
                            </p>
                        </div>
                    </div>

                    {/* Chart */}
                    <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                        <h3 className="text-lg font-semibold text-gray-900 mb-4">Evolución de Ventas</h3>
                        <div className="h-64 flex items-end gap-px relative">
                            {/* Average line */}
                            {avgLine > 0 && (
                                <div
                                    className="absolute left-0 right-0 border-t-2 border-dashed border-red-400"
                                    style={{ bottom: `${(avgLine / maxValue) * 100}%` }}
                                >
                                    <span className="absolute right-0 -top-5 text-xs text-red-500 bg-white px-1">
                                        Prom: {formatCurrency(avgLine)}
                                    </span>
                                </div>
                            )}

                            {chartDays.map((day, idx) => {
                                const value = chartValues[idx];
                                const height = (value / maxValue) * 100;
                                const dayNum = day.split('-')[2];
                                return (
                                    <div
                                        key={day}
                                        className="flex-1 flex flex-col items-center"
                                    >
                                        <div
                                            className="w-full bg-primary/80 hover:bg-primary transition-colors rounded-t cursor-pointer group relative"
                                            style={{ height: `${Math.max(height, 2)}%` }}
                                            title={`${day}: ${formatCurrency(value)}`}
                                        >
                                            <div className="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none z-10">
                                                {formatCurrency(value)}
                                            </div>
                                        </div>
                                        <span className="text-[10px] text-gray-400 mt-1">{dayNum}</span>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    {/* Sales Table */}
                    <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div className="p-4 border-b border-gray-100 flex items-center justify-between">
                            <h3 className="text-lg font-semibold text-gray-900">Detalle de Ventas</h3>
                            <div className="flex items-center gap-2">
                                <label className="text-sm text-gray-600">Mostrar:</label>
                                <select
                                    value={itemsPerPage}
                                    onChange={(e) => setItemsPerPage(Number(e.target.value))}
                                    className="rounded-md border-gray-300 text-sm"
                                >
                                    <option value={10}>10</option>
                                    <option value={25}>25</option>
                                    <option value={50}>50</option>
                                    <option value={100}>100</option>
                                    <option value={-1}>Todas</option>
                                </select>
                            </div>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead className="bg-gray-50">
                                    <tr>
                                        {[
                                            { key: 'id', label: 'ID' },
                                            { key: 'receipt_number', label: 'Recibo' },
                                            { key: 'total', label: 'Total' },
                                            { key: 'paid', label: 'Pagado' },
                                            { key: 'change', label: 'Cambio' },
                                            { key: 'payment_method', label: 'Método' },
                                            { key: 'status', label: 'Estado' },
                                            { key: 'created_at', label: 'Fecha' },
                                        ].map((col) => (
                                            <th
                                                key={col.key}
                                                onClick={() => handleSort(col.key)}
                                                className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                                            >
                                                <div className="flex items-center gap-1">
                                                    {col.label}
                                                    {sortColumn === col.key && (
                                                        <ChevronDown className={`w-4 h-4 transition-transform ${sortDirection === 'asc' ? 'rotate-180' : ''}`} />
                                                    )}
                                                </div>
                                            </th>
                                        ))}
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acción</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {sortedData.length === 0 ? (
                                        <tr>
                                            <td colSpan={9} className="px-4 py-12 text-center text-gray-500">
                                                No hay ventas en el período seleccionado.
                                            </td>
                                        </tr>
                                    ) : (
                                        sortedData.map((sale) => (
                                            <tr key={sale.id} className="hover:bg-gray-50">
                                                <td className="px-4 py-3 text-sm text-gray-900">{sale.id}</td>
                                                <td className="px-4 py-3 text-sm text-gray-900 font-mono">{sale.receipt_number}</td>
                                                <td className="px-4 py-3 text-sm text-gray-900 font-medium">{formatCurrency(sale.total)}</td>
                                                <td className="px-4 py-3 text-sm text-gray-600">{formatCurrency(sale.paid)}</td>
                                                <td className="px-4 py-3 text-sm text-gray-600">{formatCurrency(sale.change)}</td>
                                                <td className="px-4 py-3 text-sm">
                                                    <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                                                        sale.payment_method === 'cash' ? 'bg-green-100 text-green-700' :
                                                        sale.payment_method === 'debit' ? 'bg-blue-100 text-blue-700' :
                                                        sale.payment_method === 'credit' ? 'bg-purple-100 text-purple-700' :
                                                        'bg-gray-100 text-gray-700'
                                                    }`}>
                                                        {sale.payment_method === 'cash' ? 'Efectivo' :
                                                         sale.payment_method === 'debit' ? 'Débito' :
                                                         sale.payment_method === 'credit' ? 'Crédito' :
                                                         'Transferencia'}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3 text-sm">
                                                    <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                                                        sale.status === 'complete_refund' ? 'bg-red-100 text-red-700' :
                                                        sale.status === 'partial_refund' ? 'bg-amber-100 text-amber-700' :
                                                        'bg-green-100 text-green-700'
                                                    }`}>
                                                        {sale.status === 'complete_refund' ? '🔄 Devuelta' :
                                                         sale.status === 'partial_refund' ? '⚠️ Dev. Parcial' :
                                                         '✅ Completada'}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3 text-sm text-gray-600">{formatDate(sale.created_at)}</td>
                                                <td className="px-4 py-3">
                                                    <button
                                                        onClick={() => window.open(tRoute('sales.show', { sale: sale.id }), '_blank')}
                                                        className="text-primary hover:text-primary/80 flex items-center gap-1 text-sm"
                                                    >
                                                        <Printer className="w-4 h-4" />
                                                        Imprimir
                                                    </button>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <div className="p-4 border-t border-gray-100 text-sm text-gray-500">
                            Mostrando {sortedData.length} de {salesData.length} ventas
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
