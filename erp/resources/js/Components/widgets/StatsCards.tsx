import { useState, useEffect, useMemo } from 'react';
import { TrendingUp, Package, ShoppingCart, Clock, AlertTriangle, Loader2 } from 'lucide-react';
import { useTenantRoute } from '@/Hooks/useTenantRoute';

interface StatsData {
    todaySales: number;
    todayTransactions: number;
    averageTicket: number;
    productsCount: number;
    lowStockCount: number;
    lastSale: {
        total: number;
        time: string;
    } | null;
}

export default function StatsCards() {
    const tRoute = useTenantRoute();
    const [data, setData] = useState<StatsData | null>(null);
    const [loading, setLoading] = useState(true);

    // Compute API URL once
    const apiUrl = useMemo(() => tRoute('api.widgets.stats'), []);

    useEffect(() => {
        const fetchStats = async () => {
            try {
                const response = await fetch(apiUrl);
                if (!response.ok) throw new Error('Failed to fetch');
                const result = await response.json();
                setData(result);
            } catch (err) {
                console.error('Stats fetch error:', err);
            } finally {
                setLoading(false);
            }
        };

        fetchStats();
    }, [apiUrl]);

    const formatCurrency = (value: number) => {
        return new Intl.NumberFormat('es-CL', {
            style: 'currency',
            currency: 'CLP',
            minimumFractionDigits: 0
        }).format(value);
    };

    if (loading) {
        return (
            <div className="space-y-4">
                {[1, 2, 3].map((i) => (
                    <div key={i} className="bg-card rounded-xl p-5 border border-border animate-pulse">
                        <div className="flex items-center gap-3 mb-3">
                            <div className="p-2.5 bg-secondary rounded-xl w-10 h-10" />
                            <div className="h-4 w-20 bg-secondary rounded" />
                        </div>
                        <div className="h-8 w-24 bg-secondary rounded mb-1" />
                        <div className="h-3 w-16 bg-secondary rounded" />
                    </div>
                ))}
            </div>
        );
    }

    return (
        <div className="space-y-4">
            {/* Ventas Hoy */}
            <div className="bg-card rounded-xl p-5 border border-border">
                <div className="flex items-center gap-3 mb-3">
                    <div className="p-2.5 bg-primary/10 rounded-xl">
                        <TrendingUp className="w-5 h-5 text-primary" />
                    </div>
                    <span className="text-muted-foreground text-sm font-medium">Ventas Hoy</span>
                </div>
                <p className="text-3xl font-bold text-foreground">{formatCurrency(data?.todaySales || 0)}</p>
                <p className="text-xs text-muted-foreground mt-1">{data?.todayTransactions || 0} transacciones</p>
            </div>

            {/* Productos */}
            <div className="bg-card rounded-xl p-5 border border-border">
                <div className="flex items-center gap-3 mb-3">
                    <div className="p-2.5 bg-accent/10 rounded-xl">
                        <Package className="w-5 h-5 text-accent" />
                    </div>
                    <span className="text-muted-foreground text-sm font-medium">Productos</span>
                </div>
                <p className="text-3xl font-bold text-foreground">{data?.productsCount || 0}</p>
                <p className="text-xs text-muted-foreground mt-1 flex items-center gap-1">
                    {(data?.lowStockCount || 0) > 0 && (
                        <>
                            <AlertTriangle className="w-3 h-3 text-amber-500" />
                            <span className="text-amber-500">{data?.lowStockCount} con stock bajo</span>
                        </>
                    )}
                    {(data?.lowStockCount || 0) === 0 && 'En inventario'}
                </p>
            </div>

            {/* Ticket Promedio */}
            <div className="bg-card rounded-xl p-5 border border-border">
                <div className="flex items-center gap-3 mb-3">
                    <div className="p-2.5 bg-green-500/10 rounded-xl">
                        <ShoppingCart className="w-5 h-5 text-green-500" />
                    </div>
                    <span className="text-muted-foreground text-sm font-medium">Ticket Promedio</span>
                </div>
                <p className="text-3xl font-bold text-foreground">{formatCurrency(data?.averageTicket || 0)}</p>
                <p className="text-xs text-muted-foreground mt-1">Promedio por venta</p>
            </div>

            {/* Última Venta (opcional, si hay espacio) */}
            {data?.lastSale && (
                <div className="bg-card rounded-xl p-5 border border-border">
                    <div className="flex items-center gap-3 mb-3">
                        <div className="p-2.5 bg-purple/10 rounded-xl">
                            <Clock className="w-5 h-5 text-purple" />
                        </div>
                        <span className="text-muted-foreground text-sm font-medium">Última Venta</span>
                    </div>
                    <p className="text-3xl font-bold text-foreground">{formatCurrency(data.lastSale.total)}</p>
                    <p className="text-xs text-muted-foreground mt-1">{data.lastSale.time}</p>
                </div>
            )}
        </div>
    );
}
