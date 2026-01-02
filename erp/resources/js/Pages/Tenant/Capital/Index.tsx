import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useTenantRoute } from '@/Hooks/useTenantRoute';
import { useState, useMemo, useEffect, useRef } from 'react';
import {
    TrendingUp,
    DollarSign,
    Calculator,
    BarChart3,
    RefreshCw,
    Filter,
    ChevronDown,
    PieChart,
    Receipt,
    CalendarDays,
    Wallet,
    ArrowUpRight,
    ArrowDownRight,
} from 'lucide-react';
import 'chart.js/auto';
import { Pie, Bar } from 'react-chartjs-2';

interface Props {
    kpis: {
        monthly_sales: number;
        monthly_sales_net: number;
        monthly_iva: number;
        monthly_iva_paid: number;
        monthly_iva_f22: number;
        monthly_cmv: number;
        monthly_profit: number;
        monthly_profit_projection: number;
        monthly_cmv_projection: number;
        total_transactions: number;
        days_in_range: number;
    };
    yesterday: {
        label: string;
        sales: number;
        cmv: number;
        profit: number;
    };
    chartData: Array<{ date: string; sales: number; profit: number }>;
    salesBreakdown: Array<{
        receipt_number: number;
        payment_method: string;
        created_at: string;
        product_name: string;
        quantity: number;
        sale_bruto: number;
        sale_neto: number;
        sale_iva: number;
        cmv: number;
        profit: number;
    }>;
    filters: {
        month: string | null;
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

export default function Index({ kpis, yesterday, chartData, salesBreakdown, filters, monthOptions }: Props) {
    const tRoute = useTenantRoute();

    const [selectedMonth, setSelectedMonth] = useState(filters.month || monthOptions[0]?.value);
    const [startDate, setStartDate] = useState(filters.start_date || '');
    const [endDate, setEndDate] = useState(filters.end_date || '');
    const [itemsPerPage, setItemsPerPage] = useState(25);

    const handleMonthChange = (month: string) => {
        setSelectedMonth(month);
        setStartDate('');
        setEndDate('');
        router.get(tRoute('capital.index'), { month }, { preserveState: true });
    };

    const handleDateRangeFilter = () => {
        if (startDate && endDate) {
            router.get(tRoute('capital.index'), { start_date: startDate, end_date: endDate }, { preserveState: true });
        }
    };

    const handleResetFilters = () => {
        router.get(tRoute('capital.index'), {}, { preserveState: false });
    };

    // Pie chart data (CMV vs Profit)
    const pieChartData = {
        labels: ['Capital de Reinversión (CMV)', 'Capital de Operaciones (Utilidad)'],
        datasets: [{
            data: [kpis.monthly_cmv, kpis.monthly_profit],
            backgroundColor: ['#f97316', '#22c55e'],
            hoverOffset: 10,
        }],
    };

    // Bar chart data (Daily trend)
    const barChartData = {
        labels: chartData.map(d => d.date.split('-')[2]),
        datasets: [
            {
                type: 'bar' as const,
                label: 'Venta Bruta Diaria',
                data: chartData.map(d => d.sales),
                backgroundColor: 'rgba(59, 130, 246, 0.6)',
                yAxisID: 'y',
            },
            {
                type: 'line' as const,
                label: 'Utilidad Bruta Diaria',
                data: chartData.map(d => d.profit),
                borderColor: '#22c55e',
                backgroundColor: '#22c55e',
                yAxisID: 'y1',
                tension: 0.3,
                pointRadius: 3,
            },
        ],
    };

    const displayedSales = useMemo(() => {
        return itemsPerPage === -1 ? salesBreakdown : salesBreakdown.slice(0, itemsPerPage);
    }, [salesBreakdown, itemsPerPage]);

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-3">
                    <span className="text-2xl">💰</span>
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Análisis de Capital
                    </h2>
                </div>
            }
        >
            <Head title="Análisis de Capital" />

            <div className="py-8">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                    {/* Navigation */}
                    <div className="flex gap-2 text-sm">
                        <button className="px-4 py-2 rounded-lg bg-primary text-white font-medium">
                            Análisis de Utilidad
                        </button>
                        <button
                            onClick={() => router.get(tRoute('capital.suppliers'))}
                            className="px-4 py-2 rounded-lg bg-white border border-gray-200 text-gray-700 hover:bg-gray-50"
                        >
                            Capital por Proveedor
                        </button>
                    </div>

                    {/* Filters */}
                    <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                        <div className="flex flex-wrap items-center gap-4">
                            <div className="flex items-center gap-2">
                                <label className="text-sm font-medium text-gray-700">Mes:</label>
                                <select
                                    value={selectedMonth}
                                    onChange={(e) => handleMonthChange(e.target.value)}
                                    className="rounded-md border-gray-300 text-sm"
                                >
                                    <option value="">-- Seleccionar Mes --</option>
                                    {monthOptions.map((opt) => (
                                        <option key={opt.value} value={opt.value}>{opt.label}</option>
                                    ))}
                                </select>
                            </div>

                            <div className="h-6 w-px bg-gray-200" />

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
                    </div>

                    {/* KPI Cards */}
                    <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4">
                        {/* Venta Bruta */}
                        <KpiCard
                            title="Venta Total (Bruto)"
                            value={formatCurrency(kpis.monthly_sales)}
                            icon={<DollarSign className="w-5 h-5" />}
                            color="blue"
                        />
                        {/* Venta Neta */}
                        <KpiCard
                            title="Venta Neta"
                            value={formatCurrency(kpis.monthly_sales_net)}
                            icon={<Wallet className="w-5 h-5" />}
                            color="indigo"
                        />
                        {/* IVA Recaudado */}
                        <KpiCard
                            title="IVA Recaudado"
                            value={formatCurrency(kpis.monthly_iva)}
                            icon={<Receipt className="w-5 h-5" />}
                            color="amber"
                        />
                        {/* IVA Pagado */}
                        <KpiCard
                            title="IVA Pagado"
                            value={formatCurrency(kpis.monthly_iva_paid)}
                            icon={<Calculator className="w-5 h-5" />}
                            color="orange"
                        />
                        {/* IVA F22 */}
                        <KpiCard
                            title="IVA F22"
                            value={formatCurrency(kpis.monthly_iva_f22)}
                            icon={kpis.monthly_iva_f22 >= 0 ? <ArrowUpRight className="w-5 h-5" /> : <ArrowDownRight className="w-5 h-5" />}
                            color={kpis.monthly_iva_f22 >= 0 ? 'red' : 'green'}
                        />
                        {/* Transacciones */}
                        <KpiCard
                            title="Transacciones"
                            value={kpis.total_transactions.toLocaleString('es-CL')}
                            icon={<BarChart3 className="w-5 h-5" />}
                            color="purple"
                        />
                        {/* CMV */}
                        <KpiCard
                            title="CMV (Reinversión)"
                            value={formatCurrency(kpis.monthly_cmv)}
                            icon={<TrendingUp className="w-5 h-5" />}
                            color="orange"
                        />
                        {/* Utilidad */}
                        <KpiCard
                            title="Capital Operaciones"
                            value={formatCurrency(kpis.monthly_profit)}
                            icon={<TrendingUp className="w-5 h-5" />}
                            color="green"
                        />
                        {/* Proyección CMV */}
                        <KpiCard
                            title="Proy. Inversión"
                            value={formatCurrency(kpis.monthly_cmv_projection)}
                            icon={<CalendarDays className="w-5 h-5" />}
                            color="slate"
                        />
                        {/* Proyección Utilidad */}
                        <KpiCard
                            title={`Proy. Utilidad (${kpis.days_in_range}d)`}
                            value={formatCurrency(kpis.monthly_profit_projection)}
                            icon={<CalendarDays className="w-5 h-5" />}
                            color="emerald"
                        />
                        {/* Yesterday Profit */}
                        <KpiCard
                            title={`Utilidad (${yesterday.label})`}
                            value={formatCurrency(yesterday.profit)}
                            icon={<TrendingUp className="w-5 h-5" />}
                            color="green"
                        />
                        {/* Yesterday CMV */}
                        <KpiCard
                            title={`CMV (${yesterday.label})`}
                            value={formatCurrency(yesterday.cmv)}
                            icon={<TrendingUp className="w-5 h-5" />}
                            color="orange"
                        />
                    </div>

                    {/* Charts Row */}
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        {/* Pie Chart */}
                        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                            <h3 className="text-lg font-semibold text-gray-900 mb-4">Proporción Utilidad vs CMV</h3>
                            <div className="h-64">
                                {kpis.monthly_sales_net > 0 ? (
                                    <Pie data={pieChartData} options={{ maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }} />
                                ) : (
                                    <div className="h-full flex items-center justify-center text-gray-400">
                                        Sin datos para el período
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Bar + Line Chart */}
                        <div className="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                            <h3 className="text-lg font-semibold text-gray-900 mb-4">Utilidad vs Venta Diaria</h3>
                            <div className="h-64">
                                {chartData.length > 0 ? (
                                    <Bar
                                        data={barChartData as any}
                                        options={{
                                            maintainAspectRatio: false,
                                            interaction: { mode: 'index', intersect: false },
                                            scales: {
                                                y: { type: 'linear', position: 'left', title: { display: true, text: 'Venta Bruta' } },
                                                y1: { type: 'linear', position: 'right', title: { display: true, text: 'Utilidad' }, grid: { drawOnChartArea: false } },
                                            },
                                        }}
                                    />
                                ) : (
                                    <div className="h-full flex items-center justify-center text-gray-400">
                                        Sin datos para el período
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Sales Breakdown Table */}
                    <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div className="p-4 border-b border-gray-100 flex items-center justify-between">
                            <h3 className="text-lg font-semibold text-gray-900">Desglose de Ítems Vendidos</h3>
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
                            <table className="w-full text-sm">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-medium text-gray-500">Fecha</th>
                                        <th className="px-4 py-3 text-left font-medium text-gray-500">Recibo</th>
                                        <th className="px-4 py-3 text-left font-medium text-gray-500">Producto</th>
                                        <th className="px-4 py-3 text-right font-medium text-gray-500">Cant.</th>
                                        <th className="px-4 py-3 text-right font-medium text-gray-500">Venta</th>
                                        <th className="px-4 py-3 text-right font-medium text-gray-500">Neto</th>
                                        <th className="px-4 py-3 text-right font-medium text-amber-600">IVA</th>
                                        <th className="px-4 py-3 text-right font-medium text-orange-600">CMV</th>
                                        <th className="px-4 py-3 text-right font-medium text-green-600">Utilidad</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {displayedSales.length === 0 ? (
                                        <tr>
                                            <td colSpan={9} className="px-4 py-12 text-center text-gray-500">
                                                No hay ventas en el período seleccionado.
                                            </td>
                                        </tr>
                                    ) : (
                                        displayedSales.map((item, idx) => (
                                            <tr key={idx} className="hover:bg-gray-50">
                                                <td className="px-4 py-3 text-gray-600">{formatDate(item.created_at)}</td>
                                                <td className="px-4 py-3 font-mono text-gray-900">{item.receipt_number}</td>
                                                <td className="px-4 py-3 text-gray-900 max-w-xs truncate">{item.product_name}</td>
                                                <td className="px-4 py-3 text-right text-gray-600">{item.quantity}</td>
                                                <td className="px-4 py-3 text-right text-gray-900 font-medium">{formatCurrency(item.sale_bruto)}</td>
                                                <td className="px-4 py-3 text-right text-gray-600">{formatCurrency(item.sale_neto)}</td>
                                                <td className="px-4 py-3 text-right text-amber-600">{formatCurrency(item.sale_iva)}</td>
                                                <td className="px-4 py-3 text-right text-orange-600">{formatCurrency(item.cmv)}</td>
                                                <td className="px-4 py-3 text-right text-green-600 font-semibold">{formatCurrency(item.profit)}</td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <div className="p-4 border-t border-gray-100 text-sm text-gray-500">
                            Mostrando {displayedSales.length} de {salesBreakdown.length} ítems
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

// KPI Card Component
function KpiCard({ title, value, icon, color }: { title: string; value: string; icon: React.ReactNode; color: string }) {
    const colorClasses: Record<string, string> = {
        blue: 'bg-blue-50 text-blue-700 border-blue-200',
        indigo: 'bg-indigo-50 text-indigo-700 border-indigo-200',
        amber: 'bg-amber-50 text-amber-700 border-amber-200',
        orange: 'bg-orange-50 text-orange-700 border-orange-200',
        green: 'bg-green-50 text-green-700 border-green-200',
        emerald: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        red: 'bg-red-50 text-red-700 border-red-200',
        purple: 'bg-purple-50 text-purple-700 border-purple-200',
        slate: 'bg-slate-50 text-slate-700 border-slate-200',
    };

    return (
        <div className={`rounded-xl p-4 border ${colorClasses[color] || colorClasses.slate}`}>
            <div className="flex items-center justify-between mb-2">
                <h3 className="text-xs font-medium opacity-80 truncate">{title}</h3>
                <span className="opacity-60">{icon}</span>
            </div>
            <p className="text-lg font-bold truncate">{value}</p>
        </div>
    );
}
