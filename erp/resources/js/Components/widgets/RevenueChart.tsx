import { useState, useEffect, useMemo } from 'react';
import { ApexOptions } from 'apexcharts';
import { Calendar, Loader2 } from 'lucide-react';
import { useTenantRoute } from '@/Hooks/useTenantRoute';

type TimeRange = 'today' | 'week' | 'month';

interface RevenueData {
    todayData: number[];
    weekData: number[];
    monthData: number[];
    totalRevenue: number;
    percentChange: number;
    todayTotal: number;
}

export default function RevenueChart() {
    const tRoute = useTenantRoute();
    const [timeRange, setTimeRange] = useState<TimeRange>('week');
    const [Chart, setChart] = useState<any>(null);
    const [data, setData] = useState<RevenueData | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    // Compute API URL once
    const apiUrl = useMemo(() => tRoute('api.widgets.revenue'), []);

    // Dynamic import for ApexCharts
    useEffect(() => {
        import('react-apexcharts').then((mod) => {
            setChart(() => mod.default);
        });
    }, []);

    // Fetch revenue data from API
    useEffect(() => {
        const fetchData = async () => {
            try {
                setLoading(true);
                const response = await fetch(apiUrl);
                if (!response.ok) throw new Error('Failed to fetch');
                const result = await response.json();
                setData(result);
            } catch (err) {
                setError('Error al cargar datos');
                console.error('Revenue fetch error:', err);
            } finally {
                setLoading(false);
            }
        };

        fetchData();
    }, [apiUrl]);

    const getChartData = () => {
        if (!data) return { categories: [], data: [] };

        switch (timeRange) {
            case 'today':
                return {
                    categories: ['2 AM', '4 AM', '6 AM', '8 AM', '10 AM', '12 PM', '2 PM', '4 PM', '6 PM', '8 PM', '10 PM', '12 AM'],
                    data: data.todayData
                };
            case 'week':
                return {
                    categories: ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
                    data: data.weekData
                };
            case 'month':
                return {
                    categories: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
                    data: data.monthData
                };
        }
    };

    const chartData = getChartData();

    const chartOptions: ApexOptions = {
        chart: {
            type: 'bar',
            height: 280,
            toolbar: { show: false },
            fontFamily: 'Inter, sans-serif',
        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: '70%',
                borderRadius: 4
            }
        },
        colors: ['var(--primary)'],
        dataLabels: { enabled: false },
        stroke: { show: true, width: 0 },
        xaxis: {
            categories: chartData.categories,
            axisBorder: { color: 'var(--border)' },
            axisTicks: { show: false },
            labels: {
                style: {
                    colors: 'hsl(var(--muted-foreground))',
                    fontSize: '12px',
                    fontWeight: 500,
                    fontFamily: 'Inter, sans-serif'
                }
            }
        },
        yaxis: {
            min: 0,
            tickAmount: 5,
            labels: {
                formatter: (val) => val + 'K',
                style: {
                    colors: 'hsl(var(--muted-foreground))',
                    fontSize: '12px',
                    fontWeight: 500,
                    fontFamily: 'Inter, sans-serif'
                }
            }
        },
        grid: {
            borderColor: 'var(--border)',
            strokeDashArray: 5,
            xaxis: { lines: { show: false } },
            yaxis: { lines: { show: true } }
        },
        fill: {
            type: 'gradient',
            gradient: {
                shade: 'light',
                type: 'vertical',
                shadeIntensity: 0.1,
                gradientToColors: ['hsl(var(--primary))'],
                inverseColors: false,
                opacityFrom: 1,
                opacityTo: 0.6,
                stops: [20, 100]
            }
        },
        tooltip: {
            y: {
                formatter: (val) => '$ ' + val + ' mil'
            }
        },
        legend: { show: false }
    };

    const series = [{
        name: 'Ingresos',
        data: chartData.data
    }];

    const formatCurrency = (value: number) => {
        return new Intl.NumberFormat('es-CL', {
            style: 'currency',
            currency: 'CLP',
            minimumFractionDigits: 0
        }).format(value);
    };

    // Loading skeleton
    if (loading) {
        return (
            <div className="bg-card rounded-2xl border border-border p-6">
                <div className="flex items-center justify-between mb-4">
                    <div className="h-6 w-24 bg-secondary animate-pulse rounded" />
                    <div className="h-8 w-48 bg-secondary animate-pulse rounded" />
                </div>
                <div className="h-10 w-40 bg-secondary animate-pulse rounded mb-4" />
                <div className="h-[280px] bg-secondary/50 animate-pulse rounded flex items-center justify-center">
                    <Loader2 className="w-8 h-8 text-muted-foreground animate-spin" />
                </div>
            </div>
        );
    }

    // Error state
    if (error) {
        return (
            <div className="bg-card rounded-2xl border border-border p-6">
                <div className="h-[280px] flex items-center justify-center text-muted-foreground">
                    {error}
                </div>
            </div>
        );
    }

    return (
        <div className="bg-white rounded-3xl border border-border shadow-panel p-6">
            {/* Header */}
            <div className="flex items-center justify-between mb-4">
                <h3 className="text-lg font-semibold text-label-primary ">Ingresos</h3>
                <div className="flex items-center gap-2">
                    <div className="flex bg-secondary rounded-lg p-1">
                        {(['today', 'week', 'month'] as TimeRange[]).map((range) => (
                            <button
                                key={range}
                                onClick={() => setTimeRange(range)}
                                className={`px-3 py-1.5 text-sm font-medium rounded-md transition-colors ${
                                    timeRange === range
                                        ? 'bg-card text-foreground shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground'
                                }`}
                            >
                                {range === 'today' ? 'Hoy' : range === 'week' ? 'Semana' : 'Mes'}
                            </button>
                        ))}
                    </div>
                    <button className="p-2 text-muted-foreground hover:text-foreground hover:bg-secondary rounded-lg transition-colors">
                        <Calendar className="w-4 h-4" />
                    </button>
                </div>
            </div>

            {/* Revenue Amount */}
            <div className="mb-4">
                <div className="flex items-baseline gap-2">
                    <span className="text-3xl  text-foreground font-medium">
                        {formatCurrency(data?.totalRevenue || 0)}
                    </span>
                    <span className={`text-sm font-medium ${(data?.percentChange || 0) >= 0 ? 'text-success' : 'text-danger'}`}>
                        {(data?.percentChange || 0) >= 0 ? '+' : ''}{data?.percentChange || 0}% vs mes anterior
                    </span>
                </div>
            </div>

            {/* Chart */}
            <div className="-mx-2">
                {Chart ? (
                    <Chart
                        options={chartOptions}
                        series={series}
                        type="bar"
                        height={280}
                    />
                ) : (
                    <div className="h-[280px] flex items-center justify-center">
                        <Loader2 className="w-8 h-8 text-muted-foreground animate-spin" />
                    </div>
                )}
            </div>
        </div>
    );
}
