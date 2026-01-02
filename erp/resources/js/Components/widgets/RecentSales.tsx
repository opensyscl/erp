import { useState, useEffect, useMemo, useCallback } from 'react';
import { usePage } from '@inertiajs/react';
import { MoreHorizontal, ShoppingCart, User, Clock, CheckCircle, XCircle, Radio } from 'lucide-react';
import { useTenantRoute } from '@/Hooks/useTenantRoute';

interface SaleItem {
    id: number;
    code: string;
    total: number;
    status: string;
    itemsCount: number;
    customer: string | null;
    createdAt: string;
    timeAgo: string;
}

interface RecentSalesData {
    sales: SaleItem[];
}

export default function RecentSales() {
    const tRoute = useTenantRoute();
    const { auth } = usePage().props as any;
    const [data, setData] = useState<RecentSalesData | null>(null);
    const [loading, setLoading] = useState(true);
    const [isLive, setIsLive] = useState(false);

    // Compute API URL once
    const apiUrl = useMemo(() => tRoute('api.widgets.recent-sales'), []);

    // Get tenant ID from authenticated user
    const tenantId = auth?.user?.tenant_id;

    // Fetch data from API
    const fetchData = async (showLiveIndicator = false) => {
        try {
            const response = await fetch(apiUrl);
            if (!response.ok) throw new Error('Failed to fetch');
            const result = await response.json();

            // Check if there's new data
            if (showLiveIndicator && data && result.sales?.[0]?.id !== data.sales?.[0]?.id) {
                setIsLive(true);
                setTimeout(() => setIsLive(false), 2000);
            }

            setData(result);
        } catch (err) {
            console.error('Recent sales fetch error:', err);
        } finally {
            setLoading(false);
        }
    };

    // Initial fetch
    useEffect(() => {
        fetchData();
    }, [apiUrl]);

    // Polling every 30 seconds for real-time updates
    useEffect(() => {
        const interval = setInterval(() => {
            fetchData(true);
        }, 30000); // 30 seconds

        return () => clearInterval(interval);
    }, [apiUrl, data]);

    // Format currency
    const formatCurrency = (value: number) => {
        return new Intl.NumberFormat('es-CL', {
            style: 'currency',
            currency: 'CLP',
            minimumFractionDigits: 0
        }).format(value);
    };

    // Get status icon and color
    const getStatusInfo = (status: string) => {
        switch (status) {
            case 'completed':
                return { icon: CheckCircle, color: 'text-green-500', bg: 'bg-green-500/10' };
            case 'cancelled':
                return { icon: XCircle, color: 'text-red-500', bg: 'bg-red-500/10' };
            default:
                return { icon: Clock, color: 'text-amber-500', bg: 'bg-amber-500/10' };
        }
    };

    // Loading skeleton
    if (loading) {
        return (
            <div className="bg-card rounded-2xl border border-border p-6 h-full">
                <div className="flex items-center justify-between mb-4">
                    <div className="h-6 w-32 bg-secondary animate-pulse rounded" />
                    <div className="h-6 w-6 bg-secondary animate-pulse rounded" />
                </div>
                <div className="space-y-3">
                    {[1, 2, 3, 4, 5].map((i) => (
                        <div key={i} className="flex items-center gap-3 p-3 rounded-lg bg-secondary/30">
                            <div className="w-10 h-10 bg-secondary animate-pulse rounded-full" />
                            <div className="flex-1">
                                <div className="h-4 w-24 bg-secondary animate-pulse rounded mb-2" />
                                <div className="h-3 w-32 bg-secondary animate-pulse rounded" />
                            </div>
                            <div className="h-5 w-16 bg-secondary animate-pulse rounded" />
                        </div>
                    ))}
                </div>
            </div>
        );
    }

    // Empty state
    if (!data || data.sales.length === 0) {
        return (
            <div className="bg-card rounded-2xl border border-border p-6 h-full">
                <h3 className="text-lg font-semibold text-foreground mb-4">Ventas Recientes</h3>
                <div className="h-[300px] flex flex-col items-center justify-center text-muted-foreground">
                    <ShoppingCart className="w-12 h-12 mb-2 opacity-30" />
                    <p className="text-sm">No hay ventas recientes</p>
                </div>
            </div>
        );
    }

    return (
        <div className="bg-white rounded-3xl border shadow-panel p-6 h-full flex flex-col">
            {/* Header */}
            <div className="flex items-center justify-between mb-4">
                <div className="flex items-center gap-2">
                    <h3 className="text-base font-semibold text-label-primary">Ventas Recientes</h3>
                    {/* Live indicator */}
                    <div className={`flex items-center gap-1 transition-opacity ${isLive ? 'opacity-100' : 'opacity-0'}`}>
                        <Radio className="w-3 h-3 text-success animate-pulse" />
                        <span className="text-xs text-success font-medium">EN VIVO</span>
                    </div>
                </div>
                <button className="p-1.5 text-muted-foreground hover:text-foreground hover:bg-secondary rounded-lg transition-colors">
                    <MoreHorizontal className="w-4 h-4" />
                </button>
            </div>


            <div className="flex-1 overflow-y-auto space-y-2">
                {data.sales.slice(0, 15).map((sale, index) => {
                    const statusInfo = getStatusInfo(sale.status);
                    const StatusIcon = statusInfo.icon;

                    const isNew = index === 0 && isLive;

                    return (
                        <div
                            key={sale.id}
                            className={`flex items-center gap-3 p-2 rounded-lg hover:bg-secondary/50 transition-all group ${
                                isNew ? 'bg-success/10 ring-1 ring-success/30' : ''
                            }`}
                        >
                            {/* Status Icon */}
                            <div className={`w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 ${statusInfo.bg}`}>
                                <StatusIcon className={`w-4 h-4 ${statusInfo.color}`} />
                            </div>

                            {/* Sale Info */}
                            <div className="flex-1 min-w-0 flex items-center gap-2">
                                <span className="text-sm font-medium text-foreground whitespace-nowrap">
                                    {sale.code}
                                </span>
                                <span className="text-xs text-muted-foreground truncate">
                                    {sale.itemsCount} {sale.itemsCount === 1 ? 'prod' : 'prods'} • {sale.timeAgo}
                                </span>
                            </div>

                            {/* Total */}
                            <div className="text-right flex-shrink-0">
                                <span className="text-sm font-bold text-foreground">
                                    {formatCurrency(sale.total)}
                                </span>
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
