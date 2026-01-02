import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useTenantRoute } from '@/Hooks/useTenantRoute';
import { useState, useEffect, useRef } from 'react';
import {
    Package,
    TrendingUp,
    TrendingDown,
    Award,
    DollarSign,
    BarChart3,
    ShoppingCart,
    AlertCircle
} from 'lucide-react';

interface RankingItem {
    id: number;
    product_name: string;
    supplier_name: string;
    stock: number;
    units_sold: number;
    total_revenue: number;
    total_margin: number;
    avg_unit_margin: number;
    revenue_share_pct?: number;
    margin_share_pct?: number;
}

interface UnsoldItem {
    id: number;
    product_name: string;
    stock: number;
}

interface KPIs {
    total_products: number;
    products_with_sales: number;
    products_without_sales: number;
    most_sold_month: { name: string; units_sold: number } | null;
    most_profitable_month: { name: string; total_margin: number } | null;
    most_sold_day: { name: string; units_sold: number } | null;
    most_profitable_day: { name: string; total_margin: number } | null;
}

interface Props {
    kpis: KPIs;
    rankingGlobal: RankingItem[];
    rankingMonthly: RankingItem[];
    rankingDaily: RankingItem[];
    unsoldMonthly: UnsoldItem[];
    unsoldGlobal: UnsoldItem[];
    chartData: RankingItem[];
    selectedMonth: string;
    monthOptions: { value: string; label: string }[];
    isCurrentMonth: boolean;
}

type TabKey = 'daily' | 'monthly' | 'global' | 'unsold_monthly' | 'unsold_global';
type SortKey = 'units_sold' | 'total_revenue' | 'total_margin' | 'product_name';

export default function Index({
    kpis,
    rankingGlobal,
    rankingMonthly,
    rankingDaily,
    unsoldMonthly,
    unsoldGlobal,
    chartData,
    selectedMonth,
    monthOptions,
    isCurrentMonth
}: Props) {
    const tRoute = useTenantRoute();
    const chartRef = useRef<HTMLCanvasElement>(null);
    const chartInstanceRef = useRef<any>(null);

    const [activeTab, setActiveTab] = useState<TabKey>('monthly');
    const [chartMetric, setChartMetric] = useState<SortKey>('total_revenue');
    const [sortKey, setSortKey] = useState<SortKey>('total_revenue');
    const [sortDir, setSortDir] = useState<'asc' | 'desc'>('desc');
    const [limit, setLimit] = useState<number | 'all'>(25);

    const handleMonthChange = (month: string) => {
        router.get(tRoute('ranking.index'), { month }, { preserveState: true });
    };

    const formatCurrency = (amount: number) => {
        return '$' + Math.round(amount).toLocaleString('es-CL');
    };

    const formatNumber = (n: number, decimals = 0) => {
        return n.toLocaleString('es-CL', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
    };

    // Get data for active tab
    const getTabData = () => {
        switch (activeTab) {
            case 'daily': return rankingDaily;
            case 'monthly': return rankingMonthly;
            case 'global': return rankingGlobal;
            case 'unsold_monthly': return unsoldMonthly;
            case 'unsold_global': return unsoldGlobal;
        }
    };

    // Sort data
    const sortData = (data: any[]) => {
        if (!data || data.length === 0) return [];

        const sorted = [...data].sort((a, b) => {
            let valA = a[sortKey];
            let valB = b[sortKey];

            if (typeof valA === 'string') {
                valA = valA.toUpperCase();
                valB = valB.toUpperCase();
            } else {
                valA = parseFloat(valA) || 0;
                valB = parseFloat(valB) || 0;
            }

            let cmp = valA > valB ? 1 : valA < valB ? -1 : 0;
            return sortDir === 'asc' ? cmp : -cmp;
        });

        if (limit === 'all') return sorted;
        return sorted.slice(0, limit);
    };

    const handleSort = (key: SortKey) => {
        if (sortKey === key) {
            setSortDir(sortDir === 'asc' ? 'desc' : 'asc');
        } else {
            setSortKey(key);
            setSortDir('desc');
        }
    };

    const isUnsoldTab = activeTab === 'unsold_monthly' || activeTab === 'unsold_global';

    // Initialize Chart.js
    useEffect(() => {
        if (!chartRef.current || !chartData || chartData.length === 0) return;

        const loadChart = async () => {
            const { Chart, registerables } = await import('chart.js');
            Chart.register(...registerables);

            if (chartInstanceRef.current) {
                chartInstanceRef.current.destroy();
            }

            const labels = chartData.slice(0, 10).map(d => d.product_name);
            const values = chartData.slice(0, 10).map(d => {
                switch (chartMetric) {
                    case 'units_sold': return d.units_sold;
                    case 'total_margin': return d.total_margin;
                    default: return d.total_revenue;
                }
            });

            const colors: Record<string, string> = {
                total_revenue: 'rgba(59, 130, 246, 0.7)',
                units_sold: 'rgba(139, 92, 246, 0.7)',
                total_margin: 'rgba(34, 197, 94, 0.7)',
            };

            chartInstanceRef.current = new Chart(chartRef.current!, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: chartMetric === 'total_revenue' ? 'Ingresos' : chartMetric === 'units_sold' ? 'Unidades' : 'Margen',
                        data: values,
                        backgroundColor: colors[chartMetric],
                        borderRadius: 4,
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                    },
                    scales: {
                        x: {
                            ticks: {
                                callback: (value: any) => chartMetric === 'units_sold' ? value : '$' + Math.round(value).toLocaleString()
                            }
                        }
                    }
                }
            });
        };

        loadChart();

        return () => {
            if (chartInstanceRef.current) {
                chartInstanceRef.current.destroy();
            }
        };
    }, [chartData, chartMetric]);

    const KpiCard = ({ title, value, subtitle, icon: Icon, color }: { title: string; value: string | number; subtitle?: string; icon: any; color: string }) => (
        <div className={`bg-white rounded-xl p-4 shadow-sm border-l-4 ${color}`}>
            <div className="flex items-start justify-between">
                <div>
                    <p className="text-xs text-gray-500 mb-1">{title}</p>
                    <p className="text-lg font-bold text-gray-900 line-clamp-1">{value}</p>
                    {subtitle && <p className="text-xs text-gray-400 mt-1">{subtitle}</p>}
                </div>
                <Icon className="w-5 h-5 text-gray-400" />
            </div>
        </div>
    );

    const tabs = [
        { key: 'daily', label: 'Ventas del Día', disabled: !isCurrentMonth },
        { key: 'monthly', label: 'Ventas del Mes' },
        { key: 'global', label: 'Ventas Globales' },
        { key: 'unsold_monthly', label: 'Sin Ventas (Mes)' },
        { key: 'unsold_global', label: 'Sin Ventas (Global)' },
    ];

    return (
        <AuthenticatedLayout>
            <Head title="Ranking de Productos" />

            <div className="py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">📊 Ranking de Productos</h1>
                        <p className="text-sm text-gray-500">Análisis de ventas y rentabilidad</p>
                    </div>
                    <select
                        value={selectedMonth}
                        onChange={(e) => handleMonthChange(e.target.value)}
                        className="px-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary/20"
                    >
                        {monthOptions.map((opt) => (
                            <option key={opt.value} value={opt.value}>{opt.label}</option>
                        ))}
                    </select>
                </div>

                {/* KPI Grid */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <KpiCard title="Productos con Ventas" value={kpis.products_with_sales} icon={ShoppingCart} color="border-blue-500" />
                    <KpiCard title="Sin Ventas (Global)" value={kpis.products_without_sales} icon={AlertCircle} color="border-red-500" />
                    <KpiCard
                        title="🥇 Más Vendido (Mes)"
                        value={kpis.most_sold_month?.name || 'N/A'}
                        subtitle={kpis.most_sold_month ? `${formatNumber(kpis.most_sold_month.units_sold)} unidades` : undefined}
                        icon={Award}
                        color="border-yellow-500"
                    />
                    <KpiCard
                        title="💰 Más Rentable (Mes)"
                        value={kpis.most_profitable_month?.name || 'N/A'}
                        subtitle={kpis.most_profitable_month ? formatCurrency(kpis.most_profitable_month.total_margin) : undefined}
                        icon={DollarSign}
                        color="border-green-500"
                    />
                </div>

                {/* Day KPIs (only if current month) */}
                {isCurrentMonth && (
                    <div className="grid grid-cols-2 gap-4 mb-6">
                        <KpiCard
                            title="🥇 Más Vendido (Hoy)"
                            value={kpis.most_sold_day?.name || 'N/A'}
                            subtitle={kpis.most_sold_day ? `${formatNumber(kpis.most_sold_day.units_sold)} unidades` : undefined}
                            icon={TrendingUp}
                            color="border-purple-500"
                        />
                        <KpiCard
                            title="💰 Más Rentable (Hoy)"
                            value={kpis.most_profitable_day?.name || 'N/A'}
                            subtitle={kpis.most_profitable_day ? formatCurrency(kpis.most_profitable_day.total_margin) : undefined}
                            icon={TrendingDown}
                            color="border-emerald-500"
                        />
                    </div>
                )}

                {/* Chart Section */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
                    <div className="flex items-center justify-between mb-4">
                        <h2 className="text-lg font-semibold">Top 10 Productos</h2>
                        <div className="flex gap-2">
                            {(['total_revenue', 'units_sold', 'total_margin'] as SortKey[]).map(metric => (
                                <button
                                    key={metric}
                                    onClick={() => setChartMetric(metric)}
                                    className={`px-3 py-1.5 text-xs font-medium rounded-lg transition ${
                                        chartMetric === metric
                                            ? 'bg-primary text-white'
                                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                                    }`}
                                >
                                    {metric === 'total_revenue' ? 'Ingresos' : metric === 'units_sold' ? 'Unidades' : 'Margen'}
                                </button>
                            ))}
                        </div>
                    </div>
                    <div className="h-[400px]">
                        <canvas ref={chartRef}></canvas>
                    </div>
                </div>

                {/* Table Section */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <div className="p-4 border-b border-gray-100">
                        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                            <h2 className="text-lg font-semibold">Detalle de Ranking</h2>
                            <div className="flex items-center gap-4">
                                <div className="flex gap-1 overflow-x-auto">
                                    {tabs.map(tab => (
                                        <button
                                            key={tab.key}
                                            onClick={() => setActiveTab(tab.key as TabKey)}
                                            disabled={tab.disabled}
                                            className={`px-3 py-1.5 text-xs font-medium rounded-lg whitespace-nowrap transition ${
                                                activeTab === tab.key
                                                    ? 'bg-primary text-white'
                                                    : tab.disabled
                                                        ? 'bg-gray-50 text-gray-300 cursor-not-allowed'
                                                        : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                                            }`}
                                        >
                                            {tab.label}
                                        </button>
                                    ))}
                                </div>
                                <select
                                    value={limit}
                                    onChange={(e) => setLimit(e.target.value === 'all' ? 'all' : parseInt(e.target.value))}
                                    className="px-2 py-1 text-xs border rounded-lg"
                                >
                                    <option value={10}>10</option>
                                    <option value={25}>25</option>
                                    <option value={50}>50</option>
                                    <option value={100}>100</option>
                                    <option value="all">Todos</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left font-medium text-gray-600">#</th>
                                    <th
                                        className="px-4 py-3 text-left font-medium text-gray-600 cursor-pointer hover:text-primary"
                                        onClick={() => handleSort('product_name')}
                                    >
                                        Producto {sortKey === 'product_name' && (sortDir === 'asc' ? '↑' : '↓')}
                                    </th>
                                    {!isUnsoldTab && (
                                        <>
                                            <th className="px-4 py-3 text-center font-medium text-gray-600">Proveedor</th>
                                            <th className="px-4 py-3 text-center font-medium text-gray-600">Stock</th>
                                            <th
                                                className="px-4 py-3 text-center font-medium text-gray-600 cursor-pointer hover:text-primary"
                                                onClick={() => handleSort('units_sold')}
                                            >
                                                Unidades {sortKey === 'units_sold' && (sortDir === 'asc' ? '↑' : '↓')}
                                            </th>
                                            <th
                                                className="px-4 py-3 text-center font-medium text-gray-600 cursor-pointer hover:text-primary"
                                                onClick={() => handleSort('total_revenue')}
                                            >
                                                Ingresos {sortKey === 'total_revenue' && (sortDir === 'asc' ? '↑' : '↓')}
                                            </th>
                                            <th
                                                className="px-4 py-3 text-center font-medium text-gray-600 cursor-pointer hover:text-primary"
                                                onClick={() => handleSort('total_margin')}
                                            >
                                                Margen {sortKey === 'total_margin' && (sortDir === 'asc' ? '↑' : '↓')}
                                            </th>
                                        </>
                                    )}
                                    {isUnsoldTab && (
                                        <th className="px-4 py-3 text-center font-medium text-gray-600">Stock</th>
                                    )}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {sortData(getTabData()).length === 0 ? (
                                    <tr>
                                        <td colSpan={isUnsoldTab ? 3 : 7} className="px-4 py-12 text-center text-gray-500">
                                            No hay datos para mostrar
                                        </td>
                                    </tr>
                                ) : (
                                    sortData(getTabData()).map((item: any, index: number) => (
                                        <tr key={item.id} className="hover:bg-gray-50">
                                            <td className="px-4 py-3 text-gray-400">{index + 1}</td>
                                            <td className="px-4 py-3 font-medium">{item.product_name}</td>
                                            {!isUnsoldTab && (
                                                <>
                                                    <td className="px-4 py-3 text-center text-gray-500">{item.supplier_name}</td>
                                                    <td className="px-4 py-3 text-center">{formatNumber(item.stock)}</td>
                                                    <td className="px-4 py-3 text-center">{formatNumber(item.units_sold, 2)}</td>
                                                    <td className="px-4 py-3 text-center text-blue-600 font-medium">{formatCurrency(item.total_revenue)}</td>
                                                    <td className="px-4 py-3 text-center text-green-600 font-medium">{formatCurrency(item.total_margin)}</td>
                                                </>
                                            )}
                                            {isUnsoldTab && (
                                                <td className="px-4 py-3 text-center">{formatNumber(item.stock)}</td>
                                            )}
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
