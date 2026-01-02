import { useState, useEffect, useMemo } from 'react';
import { MoreHorizontal, Wallet, Clock, TrendingUp, CreditCard, Banknote, ArrowRightLeft } from 'lucide-react';
import { useTenantRoute } from '@/Hooks/useTenantRoute';

interface SalesBreakdown {
    count: number;
    total: number;
    cash: number;
    card: number;
    transfer: number;
    other: number;
}

interface CashStatusData {
    isOpen: boolean;
    startingCash: number;
    currentCash: number;
    openedAt: string | null;
    openedAgo: string | null;
    sales: SalesBreakdown;
    closing: {
        endingCash: number;
        totalOutgoings: number;
    } | null;
}

export default function CashStatus() {
    const tRoute = useTenantRoute();
    const [data, setData] = useState<CashStatusData | null>(null);
    const [loading, setLoading] = useState(true);

    // Compute API URL once
    const apiUrl = useMemo(() => tRoute('api.widgets.cash-status'), []);

    // Fetch data from API
    useEffect(() => {
        const fetchData = async () => {
            try {
                const response = await fetch(apiUrl);
                if (!response.ok) throw new Error('Failed to fetch');
                const result = await response.json();
                setData(result);
            } catch (err) {
                console.error('Cash status fetch error:', err);
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

    // Loading skeleton
    if (loading) {
        return (
            <div className="bg-card rounded-2xl border border-border p-5 h-full">
                <div className="flex items-center justify-between mb-4">
                    <div className="h-6 w-32 bg-secondary animate-pulse rounded" />
                    <div className="h-6 w-6 bg-secondary animate-pulse rounded" />
                </div>
                <div className="space-y-3">
                    <div className="h-12 bg-secondary animate-pulse rounded-xl" />
                    <div className="h-8 bg-secondary animate-pulse rounded" />
                    <div className="h-8 bg-secondary animate-pulse rounded" />
                </div>
            </div>
        );
    }

    // Empty state
    if (!data) {
        return (
            <div className="bg-card rounded-2xl border border-border p-5 h-full">
                <h3 className="text-lg font-semibold text-foreground mb-4">Estado de Caja</h3>
                <div className="h-[180px] flex flex-col items-center justify-center text-muted-foreground">
                    <Wallet className="w-12 h-12 mb-2 opacity-30" />
                    <p className="text-sm">Sin datos disponibles</p>
                </div>
            </div>
        );
    }

    return (
        <div className="bg-card rounded-2xl border border-border overflow-hidden h-full flex flex-col">
            {/* Header with status */}
            <div className="p-5 pb-3 flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <div className={`w-3 h-3 rounded-full ${data.isOpen ? 'bg-green-500 animate-pulse' : 'bg-red-500'}`} />
                    <div>
                        <h3 className="text-lg font-semibold text-foreground">
                            {data.isOpen ? 'Caja Abierta' : 'Caja Cerrada'}
                        </h3>
                        {data.openedAgo && (
                            <p className="text-xs text-muted-foreground">
                                Abierta {data.openedAgo}
                            </p>
                        )}
                    </div>
                </div>
                <button className="p-1.5 text-muted-foreground hover:text-foreground hover:bg-secondary rounded-lg transition-colors">
                    <MoreHorizontal className="w-4 h-4" />
                </button>
            </div>

            {/* Current Cash Balance */}
            <div className="px-5 py-3">
                <div className="bg-gradient-to-r from-primary/10 to-primary/5 rounded-xl p-4">
                    <div className="flex items-center gap-2 text-muted-foreground text-xs mb-1">
                        <Wallet className="w-3.5 h-3.5" />
                        Saldo en Caja
                    </div>
                    <p className="text-2xl font-bold text-foreground">
                        {formatCurrency(data.currentCash)}
                    </p>
                </div>
            </div>

            {/* Sales Summary */}
            <div className="flex-1 px-5 pb-3">
                <div className="text-xs text-muted-foreground mb-2 flex items-center gap-1">
                    <TrendingUp className="w-3.5 h-3.5" />
                    Ventas del día ({data.sales.count})
                </div>
                <p className="text-lg font-semibold text-foreground mb-3">
                    {formatCurrency(data.sales.total)}
                </p>

                {/* Payment Methods Breakdown */}
                <div className="space-y-2">
                    <div className="flex items-center justify-between text-sm">
                        <div className="flex items-center gap-2 text-muted-foreground">
                            <Banknote className="w-4 h-4" />
                            <span>Efectivo</span>
                        </div>
                        <span className="font-medium text-foreground">{formatCurrency(data.sales.cash)}</span>
                    </div>
                    <div className="flex items-center justify-between text-sm">
                        <div className="flex items-center gap-2 text-muted-foreground">
                            <CreditCard className="w-4 h-4" />
                            <span>Tarjeta</span>
                        </div>
                        <span className="font-medium text-foreground">{formatCurrency(data.sales.card)}</span>
                    </div>
                    <div className="flex items-center justify-between text-sm">
                        <div className="flex items-center gap-2 text-muted-foreground">
                            <ArrowRightLeft className="w-4 h-4" />
                            <span>Transferencia</span>
                        </div>
                        <span className="font-medium text-foreground">{formatCurrency(data.sales.transfer)}</span>
                    </div>
                </div>
            </div>

            {/* Footer with starting cash */}
            <div className="px-5 py-3 border-t border-border bg-secondary/30">
                <div className="flex items-center justify-between text-sm">
                    <span className="text-muted-foreground">Saldo inicial</span>
                    <span className="font-medium text-foreground">{formatCurrency(data.startingCash)}</span>
                </div>
            </div>
        </div>
    );
}
