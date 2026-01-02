import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useTenantRoute } from '@/Hooks/useTenantRoute';
import { useState, useMemo } from 'react';
import {
    TrendingUp,
    DollarSign,
    Calculator,
    RefreshCw,
    Filter,
    Users,
    Wallet,
    Receipt,
    ArrowUpRight,
    ArrowDownRight,
    CalendarDays,
} from 'lucide-react';
import 'chart.js/auto';
import { Doughnut, Bar } from 'react-chartjs-2';

interface SupplierMetric {
    supplier_id: number;
    supplier_name: string;
    total_sales_bruto: number;
    total_sales_neto: number;
    total_cmv: number;
    total_net_profit: number;
    total_iva_recaudado: number;
    total_purchase_iva: number;
    total_real_iva: number;
    margin_percent: number;
    contribution_percent: number;
}

interface Props {
    kpis: {
        total_sales_neto: number;
        total_cmv: number;
        total_net_profit: number;
        total_iva_recaudado: number;
        total_purchase_iva: number;
        total_real_iva: number;
        iva_total_final_a_pagar: number;
        count_suppliers_with_credit: number;
        total_credit_fiscal_remanente: number;
        days_in_range: number;
    };
    supplierMetrics: SupplierMetric[];
    chartData: Array<{ label: string; value: number }>;
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

const COLORS = ['#3b82f6', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316', '#6366f1', '#84cc16'];

export default function BySupplier({ kpis, supplierMetrics, chartData, filters, monthOptions }: Props) {
    const tRoute = useTenantRoute();

    const [selectedMonth, setSelectedMonth] = useState(filters.month || monthOptions[0]?.value);
    const [startDate, setStartDate] = useState(filters.start_date || '');
    const [endDate, setEndDate] = useState(filters.end_date || '');

    const handleMonthChange = (month: string) => {
        setSelectedMonth(month);
        setStartDate('');
        setEndDate('');
        router.get(tRoute('capital.suppliers'), { month }, { preserveState: true });
    };

    const handleDateRangeFilter = () => {
        if (startDate && endDate) {
            router.get(tRoute('capital.suppliers'), { start_date: startDate, end_date: endDate }, { preserveState: true });
        }
    };

    const handleResetFilters = () => {
        router.get(tRoute('capital.suppliers'), {}, { preserveState: false });
    };

    // Doughnut chart data
    const doughnutData = {
        labels: chartData.map(d => d.label),
        datasets: [{
            data: chartData.map(d => d.value),
            backgroundColor: COLORS.slice(0, chartData.length),
            hoverOffset: 10,
        }],
    };

    // Top 5 bar chart
    const top5 = useMemo(() => {
        return [...supplierMetrics].sort((a, b) => b.total_net_profit - a.total_net_profit).slice(0, 5);
    }, [supplierMetrics]);

    const barChartData = {
        labels: top5.map(s => s.supplier_name),
        datasets: [
            {
                label: 'Venta Bruta',
                data: top5.map(s => s.total_sales_bruto),
                backgroundColor: '#3b82f6',
            },
            {
                label: 'IVA Recaudado',
                data: top5.map(s => s.total_iva_recaudado),
                backgroundColor: '#f59e0b',
            },
            {
                label: 'IVA Crédito',
                data: top5.map(s => s.total_purchase_iva),
                backgroundColor: '#f97316',
            },
            {
                label: 'IVA a Pagar',
                data: top5.map(s => s.total_real_iva),
                backgroundColor: '#06b6d4',
            },
            {
                label: 'Utilidad Neta',
                data: top5.map(s => s.total_net_profit),
                backgroundColor: '#22c55e',
            },
        ],
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-3">
                    <span className="text-2xl">📊</span>
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Capital por Proveedor
                    </h2>
                </div>
            }
        >
            <Head title="Capital por Proveedor" />

            <div className="py-8">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                    {/* Navigation */}
                    <div className="flex gap-2 text-sm">
                        <button
                            onClick={() => router.get(tRoute('capital.index'))}
                            className="px-4 py-2 rounded-lg bg-white border border-gray-200 text-gray-700 hover:bg-gray-50"
                        >
                            Análisis de Utilidad
                        </button>
                        <button className="px-4 py-2 rounded-lg bg-primary text-white font-medium">
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
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <KpiCard
                            title="Venta Neta Total"
                            value={formatCurrency(kpis.total_sales_neto)}
                            icon={<DollarSign className="w-5 h-5" />}
                            color="indigo"
                        />
                        <KpiCard
                            title="CMV Total (Reinversión)"
                            value={formatCurrency(kpis.total_cmv)}
                            icon={<Wallet className="w-5 h-5" />}
                            color="orange"
                        />
                        <KpiCard
                            title="Utilidad Neta Total"
                            value={formatCurrency(kpis.total_net_profit)}
                            icon={<TrendingUp className="w-5 h-5" />}
                            color="green"
                        />
                        <KpiCard
                            title="Días del Período"
                            value={kpis.days_in_range.toString()}
                            icon={<CalendarDays className="w-5 h-5" />}
                            color="slate"
                        />
                        <KpiCard
                            title="Proveedores con Crédito"
                            value={kpis.count_suppliers_with_credit.toString()}
                            icon={<Users className="w-5 h-5" />}
                            color="orange"
                        />
                        <KpiCard
                            title="Crédito Fiscal (Remanente)"
                            value={`-${formatCurrency(kpis.total_credit_fiscal_remanente)}`}
                            icon={<ArrowDownRight className="w-5 h-5" />}
                            color="orange"
                        />
                        <KpiCard
                            title="IVA Total Final a Pagar"
                            value={formatCurrency(kpis.iva_total_final_a_pagar)}
                            icon={kpis.iva_total_final_a_pagar > 0 ? <ArrowUpRight className="w-5 h-5" /> : <Calculator className="w-5 h-5" />}
                            color={kpis.iva_total_final_a_pagar > 0 ? 'green' : 'slate'}
                        />
                        <KpiCard
                            title="IVA Recaudado Total"
                            value={formatCurrency(kpis.total_iva_recaudado)}
                            icon={<Receipt className="w-5 h-5" />}
                            color="amber"
                        />
                    </div>

                    {/* Charts Row */}
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        {/* Doughnut Chart */}
                        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                            <h3 className="text-lg font-semibold text-gray-900 mb-4">Contribución de Utilidad por Proveedor</h3>
                            <div className="h-64">
                                {chartData.length > 0 ? (
                                    <Doughnut data={doughnutData} options={{ maintainAspectRatio: false, plugins: { legend: { display: false } } }} />
                                ) : (
                                    <div className="h-full flex items-center justify-center text-gray-400">
                                        Sin datos para el período
                                    </div>
                                )}
                            </div>
                            {/* Legend */}
                            <div className="mt-4 flex flex-wrap gap-2 justify-center text-xs">
                                {chartData.slice(0, 5).map((item, idx) => (
                                    <span key={item.label} className="flex items-center gap-1">
                                        <span className="w-3 h-3 rounded-full" style={{ backgroundColor: COLORS[idx] }} />
                                        <span className="text-gray-600">{item.label}</span>
                                    </span>
                                ))}
                            </div>
                        </div>

                        {/* Bar Chart */}
                        <div className="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                            <h3 className="text-lg font-semibold text-gray-900 mb-4">Top 5 Proveedores por Capital Generado</h3>
                            <div className="h-64">
                                {top5.length > 0 ? (
                                    <Bar
                                        data={barChartData}
                                        options={{
                                            maintainAspectRatio: false,
                                            scales: {
                                                x: { stacked: false },
                                                y: {
                                                    ticks: {
                                                        callback: (value) => formatCurrency(Number(value || 0)),
                                                    },
                                                },
                                            },
                                            plugins: {
                                                tooltip: {
                                                    callbacks: {
                                                        label: (context) => `${context.dataset.label}: ${formatCurrency(context.parsed.y)}`,
                                                    },
                                                },
                                            },
                                        }}
                                    />
                                ) : (
                                    <div className="h-full flex items-center justify-center text-gray-400">
                                        Sin datos de proveedores
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Supplier Table */}
                    <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div className="p-4 border-b border-gray-100">
                            <h3 className="text-lg font-semibold text-gray-900">Desglose de Capital por Proveedor</h3>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-medium text-gray-500">Proveedor</th>
                                        <th className="px-4 py-3 text-right font-medium text-blue-600">Venta Bruta</th>
                                        <th className="px-4 py-3 text-right font-medium text-indigo-600">Venta Neta</th>
                                        <th className="px-4 py-3 text-right font-medium text-amber-600">IVA (Débito)</th>
                                        <th className="px-4 py-3 text-right font-medium text-orange-600">IVA Crédito</th>
                                        <th className="px-4 py-3 text-right font-medium text-cyan-600">IVA a Pagar</th>
                                        <th className="px-4 py-3 text-right font-medium text-orange-600">CMV</th>
                                        <th className="px-4 py-3 text-right font-medium text-green-600">Utilidad Neta</th>
                                        <th className="px-4 py-3 text-right font-medium text-gray-500">Margen (%)</th>
                                        <th className="px-4 py-3 text-right font-medium text-gray-500">Contribución (%)</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {supplierMetrics.length === 0 ? (
                                        <tr>
                                            <td colSpan={10} className="px-4 py-12 text-center text-gray-500">
                                                No hay ventas asociadas a proveedores en este período.
                                            </td>
                                        </tr>
                                    ) : (
                                        supplierMetrics.map((metric) => (
                                            <tr key={metric.supplier_id} className="hover:bg-gray-50">
                                                <td className="px-4 py-3 font-medium text-gray-900">{metric.supplier_name}</td>
                                                <td className="px-4 py-3 text-right text-blue-600 font-medium">{formatCurrency(metric.total_sales_bruto)}</td>
                                                <td className="px-4 py-3 text-right text-indigo-600">{formatCurrency(metric.total_sales_neto)}</td>
                                                <td className="px-4 py-3 text-right text-amber-600 font-semibold">{formatCurrency(metric.total_iva_recaudado)}</td>
                                                <td className="px-4 py-3 text-right text-orange-600 font-semibold">{formatCurrency(metric.total_purchase_iva)}</td>
                                                <td className={`px-4 py-3 text-right font-semibold ${metric.total_real_iva >= 0 ? 'text-green-600' : 'text-orange-600'}`}>
                                                    {formatCurrency(metric.total_real_iva)}
                                                </td>
                                                <td className="px-4 py-3 text-right text-orange-600">{formatCurrency(metric.total_cmv)}</td>
                                                <td className="px-4 py-3 text-right text-green-600 font-semibold">{formatCurrency(metric.total_net_profit)}</td>
                                                <td className="px-4 py-3 text-right text-gray-600">{metric.margin_percent.toFixed(1)}%</td>
                                                <td className="px-4 py-3 text-right text-gray-900 font-semibold">{metric.contribution_percent.toFixed(1)}%</td>
                                            </tr>
                                        ))
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
        cyan: 'bg-cyan-50 text-cyan-700 border-cyan-200',
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
