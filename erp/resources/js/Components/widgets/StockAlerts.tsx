import { useState, useEffect, useMemo } from 'react';
import { MoreHorizontal, AlertTriangle, Package, X, ChevronRight, Loader2 } from 'lucide-react';
import { useTenantRoute } from '@/Hooks/useTenantRoute';
import {
    Drawer,
    DrawerClose,
    DrawerContent,
    DrawerDescription,
    DrawerHeader,
    DrawerTitle,
} from '@/components/ui/drawer';

interface StockCategory {
    name: string;
    count: number;
    percentage: number;
    color: string;
    severity: string;
    slug?: string;
}

interface StockAlertsData {
    categories: StockCategory[];
    total: number;
    alertCount: number;
}

interface ProductItem {
    id: number;
    name: string;
    sku: string;
    stock: number;
    minStock: number;
    price: number;
    image: string | null;
}

export default function StockAlerts() {
    const tRoute = useTenantRoute();
    const [data, setData] = useState<StockAlertsData | null>(null);
    const [loading, setLoading] = useState(true);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [selectedCategory, setSelectedCategory] = useState<StockCategory | null>(null);
    const [products, setProducts] = useState<ProductItem[]>([]);
    const [loadingProducts, setLoadingProducts] = useState(false);

    // Compute API URL once
    const apiUrl = useMemo(() => tRoute('api.widgets.stock-alerts'), []);

    // Fetch data from API
    useEffect(() => {
        const fetchData = async () => {
            try {
                const response = await fetch(apiUrl);
                if (!response.ok) throw new Error('Failed to fetch');
                const result = await response.json();
                setData(result);
            } catch (err) {
                console.error('Stock alerts fetch error:', err);
            } finally {
                setLoading(false);
            }
        };

        fetchData();
    }, [apiUrl]);

    // Handle category click
    const handleCategoryClick = async (category: StockCategory) => {
        setSelectedCategory(category);
        setDrawerOpen(true);
        setLoadingProducts(true);
        setProducts([]);

        try {
            // Use provided slug or fallback to severity for backward compat
            const slug = category.slug || category.severity;

            // Build URL by appending slug to stock-alerts URL
            const productsUrl = `${apiUrl}/${slug}`;
            const response = await fetch(productsUrl);
            if (!response.ok) throw new Error('Failed to fetch');
            const result = await response.json();
            setProducts(result.products || []);
        } catch (err) {
            console.error('Products fetch error:', err);
        } finally {
            setLoadingProducts(false);
        }
    };

    // Format currency
    const formatCurrency = (value: number) => {
        return new Intl.NumberFormat('es-CL', {
            style: 'currency',
            currency: 'CLP',
            minimumFractionDigits: 0
        }).format(value);
    };

    // Get bar width based on index
    const getBarWidth = (index: number) => {
        const widths = [100, 85, 70, 55];
        return widths[index] || 55;
    };

    // Loading skeleton
    if (loading) {
        return (
            <div className="bg-card rounded-2xl border border-border p-6">
                <div className="flex items-center justify-between mb-4">
                    <div className="h-6 w-36 bg-secondary animate-pulse rounded" />
                    <div className="h-6 w-6 bg-secondary animate-pulse rounded" />
                </div>
                <div className="h-8 w-20 bg-secondary animate-pulse rounded mb-1" />
                <div className="h-4 w-32 bg-secondary animate-pulse rounded mb-6" />
                <div className="space-y-3">
                    {[100, 85, 70, 55].map((width, i) => (
                        <div key={i} className="flex items-center justify-between">
                            <div
                                className="h-10 bg-secondary animate-pulse rounded"
                                style={{ width: `${width}%` }}
                            />
                            <div className="h-4 w-8 bg-secondary animate-pulse rounded ml-3" />
                        </div>
                    ))}
                </div>
            </div>
        );
    }

    // Empty state
    if (!data) {
        return (
            <div className="bg-card rounded-2xl border border-border p-6">
                <h3 className="text-lg font-semibold text-foreground mb-4">Estado de Inventario</h3>
                <div className="h-[200px] flex items-center justify-center text-muted-foreground text-sm">
                    No hay datos de inventario
                </div>
            </div>
        );
    }

    return (
        <>
            <div className="bg-white rounded-3xl border  shadow-panel p-6">
                {/* Header */}
                <div className="flex items-center justify-between mb-4">
                    <h3 className="text-lg font-semibold text-label-primary">Estado de Inventario</h3>
                    <button className="p-1.5 text-muted-foreground hover:text-foreground hover:bg-secondary rounded-lg transition-colors">
                        <MoreHorizontal className="w-4 h-4" />
                    </button>
                </div>

                {/* Summary */}
                <div className="mb-6">
                    <div className="flex items-baseline gap-2">
                        <span className="text-3xl font-bold text-foreground">{data.total}</span>
                        <span className="text-sm text-muted-foreground">productos activos</span>
                    </div>
                    {data.alertCount > 0 && (
                        <div className="flex items-center gap-1 mt-1">
                            <AlertTriangle className="w-3.5 h-3.5 text-warning" />
                            <span className="text-sm text-warning font-medium">
                                {data.alertCount} requieren atención
                            </span>
                        </div>
                    )}
                </div>

                {/* Funnel Bars - Clickable */}
                <div className="space-y-3">
                    {data.categories.map((category, index) => {
                        const barWidth = getBarWidth(index);

                        return (
                            <button
                                key={category.name}
                                onClick={() => handleCategoryClick(category)}
                                className="w-full flex items-center justify-between group"
                            >
                                <div
                                    className="relative h-10 rounded-lg flex items-center justify-between px-3 transition-all group-hover:opacity-80"
                                    style={{
                                        width: `${barWidth}%`,
                                        backgroundColor: `${category.color}20`,
                                        borderLeft: `4px solid ${category.color}`
                                    }}
                                >
                                    <span className="text-sm font-medium text-foreground">
                                        {category.name}
                                    </span>
                                    <ChevronRight className="w-4 h-4 text-muted-foreground opacity-0 group-hover:opacity-100 transition-opacity" />
                                </div>
                                <span className="text-sm font-bold text-foreground ml-3 min-w-[32px] text-right">
                                    {category.count}
                                </span>
                            </button>
                        );
                    })}
                </div>
            </div>

            {/* Products Drawer */}
            <Drawer open={drawerOpen} onOpenChange={setDrawerOpen}>
                <DrawerContent className="max-h-[85vh]">
                    <DrawerHeader className="border-b border-border pb-4">
                        <div className="flex items-center gap-2">
                            <div
                                className="w-3 h-3 rounded-full"
                                style={{ backgroundColor: selectedCategory?.color }}
                            />
                            <DrawerTitle>{selectedCategory?.name}</DrawerTitle>
                        </div>
                        <DrawerDescription>
                            {selectedCategory?.count} productos en esta categoría
                        </DrawerDescription>
                    </DrawerHeader>

                    <div className="p-4 overflow-y-auto max-h-[60vh]">
                        {loadingProducts ? (
                            <div className="flex items-center justify-center py-8">
                                <Loader2 className="w-6 h-6 text-muted-foreground animate-spin" />
                            </div>
                        ) : products.length === 0 ? (
                            <div className="flex items-center justify-center py-8 text-muted-foreground">
                                No hay productos en esta categoría
                            </div>
                        ) : (
                            <div className="space-y-2">
                                {products.map((product) => (
                                    <div
                                        key={product.id}
                                        className="flex items-center gap-3 p-3 rounded-lg border border-border hover:bg-secondary/50 transition-colors"
                                    >
                                        {/* Product Image */}
                                        <div className="w-10 h-10 rounded-lg bg-secondary flex items-center justify-center flex-shrink-0 overflow-hidden">
                                            {product.image ? (
                                                <img
                                                    src={product.image}
                                                    alt={product.name}
                                                    className="w-full h-full object-cover"
                                                />
                                            ) : (
                                                <Package className="w-5 h-5 text-muted-foreground" />
                                            )}
                                        </div>

                                        {/* Product Info */}
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-medium text-foreground truncate">
                                                {product.name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                SKU: {product.sku || 'N/A'}
                                            </p>
                                        </div>

                                        {/* Stock Info */}
                                        <div className="text-right flex-shrink-0">
                                            <p className={`text-sm font-bold ${
                                                product.stock === 0 ? 'text-danger' :
                                                product.stock < 5 ? 'text-purple' :
                                                product.stock <= product.minStock ? 'text-warning' :
                                                'text-success'
                                            }`}>
                                                {product.stock} uds
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Min: {product.minStock}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    <div className="p-4 border-t border-border">
                        <DrawerClose asChild>
                            <button className="w-full py-2 px-4 bg-secondary text-foreground rounded-lg hover:bg-secondary/80 transition-colors">
                                Cerrar
                            </button>
                        </DrawerClose>
                    </div>
                </DrawerContent>
            </Drawer>
        </>
    );
}
