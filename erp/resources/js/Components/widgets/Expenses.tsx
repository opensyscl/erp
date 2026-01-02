import { useState, useEffect, useMemo } from 'react';
import { MoreHorizontal, TrendingUp, TrendingDown, Receipt } from 'lucide-react';
import { useTenantRoute } from '@/Hooks/useTenantRoute';

interface ExpenseCategory {
    key: string;
    label: string;
    icon: string;
    amount: number;
}

interface ExpensesData {
    total: number;
    previousTotal: number;
    changePercent: number;
    categories: ExpenseCategory[];
    topExpense: ExpenseCategory | null;
    period: string;
}

export default function Expenses() {
    const tRoute = useTenantRoute();
    const [data, setData] = useState<ExpensesData | null>(null);
    const [loading, setLoading] = useState(true);

    // Compute API URL once
    const apiUrl = useMemo(() => tRoute('api.widgets.expenses'), []);

    // Fetch data from API
    useEffect(() => {
        const fetchData = async () => {
            try {
                const response = await fetch(apiUrl);
                if (!response.ok) throw new Error('Failed to fetch');
                const result = await response.json();
                setData(result);
            } catch (err) {
                console.error('Expenses fetch error:', err);
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
                    <div className="h-6 w-36 bg-secondary animate-pulse rounded" />
                    <div className="h-6 w-6 bg-secondary animate-pulse rounded" />
                </div>
                <div className="h-10 bg-secondary animate-pulse rounded mb-4" />
                <div className="space-y-2">
                    <div className="h-6 bg-secondary animate-pulse rounded" />
                    <div className="h-6 bg-secondary animate-pulse rounded" />
                    <div className="h-6 bg-secondary animate-pulse rounded" />
                </div>
            </div>
        );
    }

    // Empty state
    if (!data) {
        return (
            <div className="bg-card rounded-2xl border border-border p-5 h-full">
                <h3 className="text-lg font-semibold text-foreground mb-4">Gastos del Mes</h3>
                <div className="h-[180px] flex flex-col items-center justify-center text-muted-foreground">
                    <Receipt className="w-12 h-12 mb-2 opacity-30" />
                    <p className="text-sm">Sin gastos registrados</p>
                </div>
            </div>
        );
    }

    const isPositiveChange = data.changePercent > 0;

    return (
        <div className="bg-card rounded-2xl border border-border overflow-hidden h-full flex flex-col">
            {/* Header */}
            <div className="p-5 pb-3 flex items-center justify-between">
                <h3 className="text-lg font-semibold text-foreground">Gastos del Mes</h3>
                <button className="p-1.5 text-muted-foreground hover:text-foreground hover:bg-secondary rounded-lg transition-colors">
                    <MoreHorizontal className="w-4 h-4" />
                </button>
            </div>

            {/* Total with change indicator */}
            <div className="px-5 pb-3">
                <div className="flex items-baseline gap-3">
                    <p className="text-2xl font-bold text-foreground">
                        {formatCurrency(data.total)}
                    </p>
                    {data.changePercent !== 0 && (
                        <div className={`flex items-center gap-1 text-xs font-medium ${
                            isPositiveChange ? 'text-red-500' : 'text-green-500'
                        }`}>
                            {isPositiveChange ? (
                                <TrendingUp className="w-3.5 h-3.5" />
                            ) : (
                                <TrendingDown className="w-3.5 h-3.5" />
                            )}
                            <span>{Math.abs(data.changePercent)}%</span>
                        </div>
                    )}
                </div>
                <p className="text-xs text-muted-foreground mt-0.5">
                    vs mes anterior: {formatCurrency(data.previousTotal)}
                </p>
            </div>

            {/* Categories list */}
            <div className="flex-1 px-5 pb-3 overflow-y-auto">
                <div className="space-y-2">
                    {data.categories.slice(0, 6).map((cat) => (
                        <div key={cat.key} className="flex items-center justify-between text-sm">
                            <div className="flex items-center gap-2">
                                <span className="text-base">{cat.icon}</span>
                                <span className="text-muted-foreground">{cat.label}</span>
                            </div>
                            <span className="font-medium text-foreground">{formatCurrency(cat.amount)}</span>
                        </div>
                    ))}
                </div>
            </div>

            {/* Top expense highlight */}
            {data.topExpense && (
                <div className="px-5 py-3 border-t border-border bg-orange-50 dark:bg-orange-950/20">
                    <div className="flex items-center gap-2 text-xs text-orange-600 dark:text-orange-400">
                        <span className="text-base">{data.topExpense.icon}</span>
                        <span>Mayor gasto: <strong>{data.topExpense.label}</strong></span>
                    </div>
                </div>
            )}
        </div>
    );
}
