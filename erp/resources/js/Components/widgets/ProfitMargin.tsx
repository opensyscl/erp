import { useState, useEffect, useMemo, lazy, Suspense } from 'react';
import { MoreHorizontal, TrendingUp, TrendingDown } from 'lucide-react';
import { useTenantRoute } from '@/Hooks/useTenantRoute';

// Lazy import for ApexCharts
const Chart = lazy(() => import('react-apexcharts'));

interface OrderStatus {
    count: number;
    percentage: number;
}

interface ProfitMarginData {
    revenue: number;
    costs: number;
    profit: number;
    marginPercentage: number;
    totalOrders: number;
    orderStatus: {
        completed: OrderStatus;
        cancelled: OrderStatus;
        refunded: OrderStatus;
    };
    period: string;
}

export default function ProfitMargin({ enableGif = false }: { enableGif?: boolean }) {
    const tRoute = useTenantRoute();
    const [data, setData] = useState<ProfitMarginData | null>(null);
    const [loading, setLoading] = useState(true);

    // Compute API URL once
    const apiUrl = useMemo(() => tRoute('api.widgets.profit-margin'), []);

    // Fetch data from API
    useEffect(() => {
        const fetchData = async () => {
            try {
                const response = await fetch(apiUrl);
                if (!response.ok) throw new Error('Failed to fetch');
                const result = await response.json();
                setData(result);
            } catch (err) {
                console.error('Profit margin fetch error:', err);
            } finally {
                setLoading(false);
            }
        };

        fetchData();
    }, [apiUrl]);

    // Format currency
    const formatCurrency = (value: number, compact = false) => {
        if (compact && value >= 1000000) {
            return `$${(value / 1000000).toFixed(1)}M`;
        }
        if (compact && value >= 1000) {
            return `$${(value / 1000).toFixed(0)}K`;
        }
        return new Intl.NumberFormat('es-CL', {
            style: 'currency',
            currency: 'CLP',
            minimumFractionDigits: 0
        }).format(value);
    };

    // Chart options for radial bar
    const chartOptions = useMemo(() => ({
        chart: {
            type: 'radialBar' as const,
            offsetY: -10,
            sparkline: { enabled: true }
        },
        plotOptions: {
            radialBar: {
                startAngle: -95,
                endAngle: 95,
                track: {
                    background: 'rgba(255, 255, 255, 0.2)',
                    strokeWidth: '100%',
                    margin: 5
                },
                dataLabels: {
                    name: { show: false },
                    value: {
                        show: true,
                        offsetY: -30,
                        fontSize: '24px',
                        fontWeight: 700,
                        color: '#ffffff',
                        formatter: () => formatCurrency(data?.profit ?? 0, true)
                    }
                },
                hollow: {
                    size: '65%'
                }
            }
        },
        fill: {
            colors: ['#ffffff']
        },
        stroke: {
            lineCap: 'round' as const
        }
    }), [data?.profit]);

    // Loading skeleton
    if (loading) {
        return (
            <div className="bg-gradient-to-br from-primary to-primary/80 rounded-2xl border border-primary/20 p-6 h-full text-white">
                <div className="flex items-center justify-between mb-4">
                    <div className="h-6 w-32 bg-white/20 animate-pulse rounded" />
                    <div className="h-6 w-6 bg-white/20 animate-pulse rounded" />
                </div>
                <div className="flex justify-center">
                    <div className="w-40 h-40 rounded-full bg-white/20 animate-pulse" />
                </div>
            </div>
        );
    }

    // Empty state
    if (!data) {
        return (
            <div className="bg-gradient-to-br from-primary to-primary/80 rounded-2xl border border-primary/20 p-6 h-full text-white">
                <h3 className="text-base font-semibold mb-4">Margen de Ganancia</h3>
                <div className="h-[200px] flex flex-col items-center justify-center text-white/50">
                    <TrendingUp className="w-12 h-12 mb-2 opacity-30" />
                    <p className="text-sm">Sin datos este mes</p>
                </div>
            </div>
        );
    }

    const isPositiveMargin = data.marginPercentage >= 0;

    return (
        <div
            className="rounded-2xl border border-primary/20 overflow-hidden h-full flex flex-col relative"
            style={enableGif ? {
                backgroundImage: 'url(/images/wind.gif)',
                backgroundSize: 'cover',
                backgroundPosition: 'center'
            } : { backgroundColor: 'var(--primary)' }}
        >
            {/* Gradient Overlay */}
            <div className="absolute inset-0 bg-gradient-to-br from-primary/90 to-primary/70" />

            {/* Content */}
            <div className="relative z-10 flex flex-col h-full">
            {/* Header */}
            <div className="p-5 pb-0 flex items-center justify-between">
                <h3 className="text-lg font-semibold text-white">Margen de Ganancia</h3>
                <button className="p-1.5 text-white/60 hover:text-white hover:bg-white/10 rounded-lg transition-colors">
                    <MoreHorizontal className="w-4 h-4" />
                </button>
            </div>

            {/* Radial Chart */}
            <div className="flex-1 flex flex-col items-center justify-center px-5 -mt-2">
                <div className="w-full max-w-[200px]">
                    {typeof window !== 'undefined' && (
                        <Suspense fallback={<div className="h-[150px] bg-white/10 animate-pulse rounded-full" />}>
                            <Chart
                                options={chartOptions}
                                series={[Math.min(Math.max(data.marginPercentage, 0), 100)]}
                                type="radialBar"
                                height={160}
                            />
                        </Suspense>
                    )}
                </div>
                <div className="text-center -mt-6">
                    <p className="text-white/70 text-sm">{data.totalOrders} Órdenes</p>
                </div>

                {/* Revenue & Costs Breakdown */}
                <div className="flex items-center justify-between w-full mt-4 px-2">
                    <div className="flex items-center gap-2">
                        <div className="w-2 h-2 bg-white rounded" />
                        <div>
                            <p className="text-white font-bold text-lg">{formatCurrency(data.revenue, true)}</p>
                            <p className="text-white/50 text-xs">Ingresos</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <div className="w-2 h-2 bg-white/50 rounded" />
                        <div>
                            <p className="text-white font-bold text-lg">{formatCurrency(data.costs, true)}</p>
                            <p className="text-white/50 text-xs">Costos</p>
                        </div>
                    </div>
                </div>
            </div>

            {/* Order Status */}
            <div className="p-5 pt-4 border-t border-white/10">
                <h4 className="text-sm font-medium text-white mb-3">Estado de Órdenes</h4>

                {/* Progress Bar */}
                <div className="flex h-2 rounded-full overflow-hidden mb-4 bg-white/10">
                    <div
                        className="bg-white transition-all"
                        style={{ width: `${data.orderStatus.completed.percentage}%` }}
                    />
                    <div
                        className="bg-white/50 transition-all"
                        style={{ width: `${data.orderStatus.cancelled.percentage}%` }}
                    />
                    <div
                        className="bg-white/25 transition-all"
                        style={{ width: `${data.orderStatus.refunded.percentage}%` }}
                    />
                </div>

                {/* Status Legend */}
                <div className="space-y-2">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <div className="w-2 h-2 bg-white rounded" />
                            <span className="text-white/80 text-sm">Completadas</span>
                        </div>
                        <span className="text-white font-medium">{data.orderStatus.completed.percentage}%</span>
                    </div>
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <div className="w-2 h-2 bg-white/50 rounded" />
                            <span className="text-white/80 text-sm">Canceladas</span>
                        </div>
                        <span className="text-white font-medium">{data.orderStatus.cancelled.percentage}%</span>
                    </div>
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <div className="w-2 h-2 bg-white/25 rounded" />
                            <span className="text-white/80 text-sm">Reembolsadas</span>
                        </div>
                        <span className="text-white font-medium">{data.orderStatus.refunded.percentage}%</span>
                    </div>
                </div>
            </div>
            </div>
        </div>
    );
}
