import { useState, useEffect, useMemo, useRef } from 'react';
import { MoreHorizontal, PieChart } from 'lucide-react';
import { useTenantRoute } from '@/Hooks/useTenantRoute';
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js';
import { Doughnut } from 'react-chartjs-2';

ChartJS.register(ArcElement, Tooltip, Legend);

interface CategoryData {
    id: number;
    name: string;
    revenue: number;
    quantity: number;
    percentage: number;
}

interface SalesByCategoryData {
    categories: CategoryData[];
    totalRevenue: number;
    period: string;
}

// Color palette matching the design
const COLORS = [
    '#5955D1', // Primary purple
    '#7C79E0', // Lighter purple
    '#ACAAE8', // Even lighter
    '#D4D3F3', // Very light
    '#22C55E', // Green accent
    '#F59E0B', // Amber accent
];

export default function SalesByCategory() {
    const tRoute = useTenantRoute();
    const [data, setData] = useState<SalesByCategoryData | null>(null);
    const [loading, setLoading] = useState(true);
    const [hoveredIndex, setHoveredIndex] = useState<number | null>(null);
    const chartRef = useRef<any>(null);

    // Compute API URL once
    const apiUrl = useMemo(() => tRoute('api.widgets.sales-by-category'), []);

    // Fetch data from API
    useEffect(() => {
        const fetchData = async () => {
            try {
                const response = await fetch(apiUrl);
                if (!response.ok) throw new Error('Failed to fetch');
                const result = await response.json();
                setData(result);
            } catch (err) {
                console.error('Sales by category fetch error:', err);
            } finally {
                setLoading(false);
            }
        };

        fetchData();
    }, [apiUrl]);

    // Format currency
    const formatCurrency = (value: number) => {
        return new Intl.NumberFormat('es-CL', {
            style: 'currency',
            currency: 'CLP',
            minimumFractionDigits: 0
        }).format(value);
    };

    // Chart data
    const chartData = useMemo(() => {
        if (!data || data.categories.length === 0) {
            return {
                labels: [],
                datasets: [{
                    data: [],
                    backgroundColor: [],
                    borderColor: '#fff',
                    borderWidth: 3,
                    hoverBorderColor: '#fff',
                    hoverOffset: 5,
                }]
            };
        }

        return {
            labels: data.categories.map(c => c.name),
            datasets: [{
                data: data.categories.map(c => c.revenue),
                backgroundColor: COLORS.slice(0, data.categories.length),
                borderColor: '#fff',
                borderWidth: 3,
                hoverBorderColor: '#fff',
                hoverOffset: 5,
            }]
        };
    }, [data]);

    // Chart options
    const chartOptions = {
        cutout: '70%',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false,
            },
            tooltip: {
                enabled: true,
                callbacks: {
                    label: (context: any) => {
                        const value = context.raw;
                        return ` ${formatCurrency(value)}`;
                    }
                }
            }
        },
        onHover: (_event: any, elements: any[]) => {
            if (elements.length > 0) {
                setHoveredIndex(elements[0].index);
            } else {
                setHoveredIndex(null);
            }
        }
    };

    // Center text plugin
    const centerTextPlugin = {
        id: 'centerText',
        afterDraw(chart: any) {
            const { ctx, chartArea: { left, right, top, bottom } } = chart;
            const centerX = (left + right) / 2;
            const centerY = (top + bottom) / 2;

            if (!data || data.categories.length === 0) return;

            let displayValue = data.totalRevenue;
            let displayLabel = 'Total';

            if (hoveredIndex !== null && data.categories[hoveredIndex]) {
                displayValue = data.categories[hoveredIndex].revenue;
                displayLabel = data.categories[hoveredIndex].name;
            }

            ctx.save();
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';

            // Value
            ctx.font = 'bold 16px system-ui';
            ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--foreground').trim() || '#000';
            ctx.fillText(formatCurrency(displayValue), centerX, centerY - 8);

            // Label
            ctx.font = '12px system-ui';
            ctx.fillStyle = '#888';
            const truncatedLabel = displayLabel.length > 12 ? displayLabel.substring(0, 12) + '...' : displayLabel;
            ctx.fillText(truncatedLabel, centerX, centerY + 12);

            ctx.restore();
        }
    };

    // Loading skeleton
    if (loading) {
        return (
            <div className="bg-card rounded-2xl border border-border p-6 h-full">
                <div className="flex items-center justify-between mb-4">
                    <div className="h-6 w-40 bg-secondary animate-pulse rounded" />
                    <div className="h-6 w-6 bg-secondary animate-pulse rounded" />
                </div>
                <div className="flex justify-center">
                    <div className="w-40 h-40 rounded-full bg-secondary animate-pulse" />
                </div>
            </div>
        );
    }

    // Empty state
    if (!data || data.categories.length === 0) {
        return (
            <div className="bg-card rounded-2xl border border-border p-6 h-full">
                <h3 className="text-lg font-semibold text-foreground mb-4">Ventas por Categoría</h3>
                <div className="h-[200px] flex flex-col items-center justify-center text-muted-foreground">
                    <PieChart className="w-12 h-12 mb-2 opacity-30" />
                    <p className="text-sm">Sin datos este mes</p>
                </div>
            </div>
        );
    }

    return (
        <div className="bg-card rounded-2xl border border-border p-6 h-full flex flex-col">
            {/* Header */}
            <div className="flex items-center justify-between mb-4">
                <h3 className="text-lg font-semibold text-foreground">Ventas por Categoría</h3>
                <button className="p-1.5 text-muted-foreground hover:text-foreground hover:bg-secondary rounded-lg transition-colors">
                    <MoreHorizontal className="w-4 h-4" />
                </button>
            </div>

            {/* Chart */}
            <div className="flex-1 flex items-center justify-center">
                <div className="w-48 h-48 relative">
                    <Doughnut
                        ref={chartRef}
                        data={chartData}
                        options={chartOptions}
                        plugins={[centerTextPlugin]}
                    />
                </div>
            </div>

            {/* Legend */}
            <div className="mt-4 space-y-2">
                {data.categories.slice(0, 4).map((cat, index) => (
                    <div
                        key={cat.id}
                        className="flex items-center justify-between text-sm"
                        onMouseEnter={() => setHoveredIndex(index)}
                        onMouseLeave={() => setHoveredIndex(null)}
                    >
                        <div className="flex items-center gap-2">
                            <div
                                className="w-3 h-3 rounded-full shrink-0"
                                style={{ backgroundColor: COLORS[index] }}
                            />
                            <span className="text-foreground truncate max-w-[120px]">{cat.name}</span>
                        </div>
                        <span className="text-muted-foreground font-medium">{cat.percentage}%</span>
                    </div>
                ))}
            </div>
        </div>
    );
}
