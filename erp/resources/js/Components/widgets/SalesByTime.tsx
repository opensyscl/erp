import { useState, useEffect, useMemo, lazy, Suspense } from 'react';
import { MoreHorizontal, Clock } from 'lucide-react';
import { useTenantRoute } from '@/Hooks/useTenantRoute';

// Lazy import for ApexCharts (SSR safe)
const Chart = lazy(() => import('react-apexcharts'));

interface SeriesData {
    name: string;
    data: number[];
}

interface SalesByTimeData {
    series: SeriesData[];
    categories: string[];
}

const clamp01 = (n: number) => Math.min(1, Math.max(0, n));

    const normalizeHex = (hex: string) => {
        let h = hex.trim();
        if (!h) return '#000000';
        if (!h.startsWith('#')) h = `#${h}`;
        if (h.length === 4) h = `#${h[1]}${h[1]}${h[2]}${h[2]}${h[3]}${h[3]}`;
        return h.toUpperCase();
    };

    const hslToHex = (h: number, s: number, l: number) => {
        s /= 100;
        l /= 100;
        const c = (1 - Math.abs(2 * l - 1)) * s;
        const x = c * (1 - Math.abs(((h / 60) % 2) - 1));
        const m = l - c / 2;

        let r = 0, g = 0, b = 0;
        if (0 <= h && h < 60) [r, g, b] = [c, x, 0];
        else if (60 <= h && h < 120) [r, g, b] = [x, c, 0];
        else if (120 <= h && h < 180) [r, g, b] = [0, c, x];
        else if (180 <= h && h < 240) [r, g, b] = [0, x, c];
        else if (240 <= h && h < 300) [r, g, b] = [x, 0, c];
        else [r, g, b] = [c, 0, x];

        const toHex = (v: number) =>
            Math.round((v + m) * 255)
            .toString(16)
            .padStart(2, '0')
            .toUpperCase();

        return `#${toHex(r)}${toHex(g)}${toHex(b)}`;
    };

    const cssVarToHex = (raw: string, fallback = '#6366F1') => {
        const v = (raw || '').trim();
        if (!v) return fallback;

        if (v.startsWith('#')) return normalizeHex(v);

        const parts = v.split(/\s+/);
        if (parts.length >= 3) {
            const h = parseFloat(parts[0]);
            const s = parseFloat(parts[1].replace('%', ''));
            const l = parseFloat(parts[2].replace('%', ''));
            if ([h, s, l].every(Number.isFinite)) return hslToHex(h, s, l);
        }

        return fallback;
    };

    const getMixedHex = (hex: string, opacity: number, bgHex: string = '#FFFFFF') => {
        const normalize = (h: string) => {
            let color = h.replace('#', '').trim();
            if (color.length === 3) color = color[0] + color[0] + color[1] + color[1] + color[2] + color[2];
            return color;
        };

        const fg = normalize(hex);
        const bg = normalize(bgHex);

        const parse = (c: string) => ({
            r: parseInt(c.substr(0, 2), 16),
            g: parseInt(c.substr(2, 2), 16),
            b: parseInt(c.substr(4, 2), 16)
        });

        const f = parse(fg);
        const b = parse(bg);

        const mix = (ch1: number, ch2: number) => Math.round(ch1 * opacity + ch2 * (1 - opacity));

        const r = mix(f.r, b.r).toString(16).padStart(2, '0');
        const g = mix(f.g, b.g).toString(16).padStart(2, '0');
        const bl = mix(f.b, b.b).toString(16).padStart(2, '0');

        return `#${r}${g}${bl}`.toUpperCase();
    };

export default function SalesByTime() {
    const tRoute = useTenantRoute();
    const [data, setData] = useState<SalesByTimeData | null>(null);
    const [loading, setLoading] = useState(true);

    // Compute API URL once
    const apiUrl = useMemo(() => tRoute('api.widgets.sales-by-time'), []);

    const [primaryHex, setPrimaryHex] = useState('#6366F1');

    useEffect(() => {
        if (typeof window === 'undefined') return;

        const style = getComputedStyle(document.documentElement);
        const rawPrimary = style.getPropertyValue('--primary');
        setPrimaryHex(cssVarToHex(rawPrimary, '#6366F1'));
    }, []);

    // Fetch data from API
    useEffect(() => {
        const fetchData = async () => {
            try {
                const response = await fetch(apiUrl);
                if (!response.ok) throw new Error('Failed to fetch');
                const result = await response.json();
                setData(result);
            } catch (err) {
                console.error('Sales by time fetch error:', err);
            } finally {
                setLoading(false);
            }
        };

        fetchData();
    }, [apiUrl]);

    const chartOptions = useMemo(() => ({
        chart: {
            height: 250,
            type: 'heatmap' as const,
            toolbar: {
                show: false
            },
            background: 'transparent',
        },
        stroke: {
            width: 3,
            colors: ['var(--card)']
        },
        dataLabels: {
            enabled: false
        },
        plotOptions: {
            heatmap: {
                shadeIntensity: 0.95,
                radius: 8,
                distributed: false,
                colorScale: {
                    ranges: [
                        { from: 0, to: 0, color: getMixedHex(primaryHex, 0.05), name: 'Sin ventas' },
                        { from: 1, to: 5, color: getMixedHex(primaryHex, 0.2), name: 'Bajo' },
                        { from: 6, to: 15, color: getMixedHex(primaryHex, 0.5), name: 'Medio' },
                        { from: 16, to: 100, color: primaryHex, name: 'Alto' }
                    ]
                }
            }
        },
        grid: {
            show: false,
            padding: {
                left: 0,
                right: 0
            }
        },
        xaxis: {
            categories: data?.categories || [],
            axisBorder: { show: false },
            axisTicks: { show: false },
            labels: {
                style: {
                    colors: '#64748B',
                    fontSize: '12px',
                    fontWeight: 500,
                    fontFamily: 'system-ui'
                }
            }
        },
        yaxis: {
            labels: {
                style: {
                    colors: '#64748B',
                    fontSize: '12px',
                    fontWeight: 500,
                    fontFamily: 'system-ui'
                }
            }
        },
        legend: {
            show: false,
        },
        tooltip: {
            custom: function({ series, seriesIndex, dataPointIndex, w }: any) {
                const value = series[seriesIndex][dataPointIndex];
                const timeBlock = w.globals.seriesNames[seriesIndex];
                const day = w.globals.labels[dataPointIndex];
                return `<div class="px-3 py-2 bg-white border border-border rounded-lg shadow-lg">
                    <div class="text-xs text-muted-foreground">${day} - ${timeBlock}</div>
                    <div class="text-sm font-semibold text-foreground">${value} ${value === 1 ? 'orden' : 'órdenes'}</div>
                </div>`;
            }
        }
    }), [data?.categories, primaryHex]);

    // Loading skeleton
    if (loading) {
        return (
            <div className="bg-card rounded-2xl border border-border p-6 h-full">
                <div className="flex items-center justify-between mb-4">
                    <div className="h-6 w-36 bg-secondary animate-pulse rounded" />
                    <div className="h-6 w-6 bg-secondary animate-pulse rounded" />
                </div>
                <div className="grid grid-cols-7 gap-2">
                    {Array.from({ length: 42 }).map((_, i) => (
                        <div key={i} className="h-8 bg-secondary animate-pulse rounded" />
                    ))}
                </div>
            </div>
        );
    }

    // Empty state
    if (!data || data.series.length === 0) {
        return (
            <div className="bg-card rounded-2xl border border-border p-6 h-full">
                <h3 className="text-base font-semibold text-foreground mb-4">Ventas por Hora</h3>
                <div className="h-[200px] flex flex-col items-center justify-center text-muted-foreground">
                    <Clock className="w-12 h-12 mb-2 opacity-30" />
                    <p className="text-sm">Sin datos de ventas</p>
                </div>
            </div>
        );
    }

    return (
        <div className="bg-white rounded-3xl border  p-6 h-full">
            {/* Header */}
            <div className="flex items-center justify-between mb-2">
                <h3 className="text-base font-semibold text-label-primary">Ventas por Hora</h3>
                <button className="p-1.5 text-muted-foreground hover:text-foreground hover:bg-secondary rounded-lg transition-colors">
                    <MoreHorizontal className="w-4 h-4" />
                </button>
            </div>

            {/* Chart */}
            <div className="w-full">
                {typeof window !== 'undefined' && (
                    <Suspense fallback={<div className="h-[220px] bg-secondary animate-pulse rounded" />}>
                        <Chart
                            options={chartOptions}
                            series={data.series}
                            type="heatmap"
                            height={220}
                        />
                    </Suspense>
                )}
            </div>
        </div>
    );
}
