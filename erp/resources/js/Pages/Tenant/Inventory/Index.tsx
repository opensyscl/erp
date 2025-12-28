import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { useTenantRoute } from '@/Hooks/useTenantRoute';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { ChevronDown, Pencil, Copy, Archive, Trash2, RotateCcw, LayoutGrid, List, Table } from 'lucide-react';
import { ProductGridSkeleton } from '@/components/skeletons/InventorySkeleton';
import { toast } from 'sonner';

interface Category {
    id: number;
    name: string;
    products_count: number;
}

interface Supplier {
    id: number;
    name: string;
    products_count: number;
}

interface Product {
    id: number;
    name: string;
    slug: string;
    sku: string | null;
    price: number;
    cost: number;
    stock: number;
    min_stock: number;
    image: string | null;
    is_active: boolean;
    category: Category | null;
    supplier: Supplier | null;
}

interface PaginatedProducts {
    data: Product[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface Stats {
    total: number;
    lowStock: number;
    outOfStock: number;
    withoutImage: number;
    withoutSupplier: number;
    negativeStock: number;
    archived: number;
}

interface Filters {
    search?: string;
    category?: string;
    supplier?: string;
    stock_status?: string;
    sort?: string;
    dir?: string;
}

interface Props {
    products: PaginatedProducts;
    stats: Stats;
    categories: Category[];
    suppliers: Supplier[];
    filters: Filters;
}

export default function Index({ products, stats, categories, suppliers, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [category, setCategory] = useState(filters.category || '');
    const [supplier, setSupplier] = useState(filters.supplier || '');
    const [stockStatus, setStockStatus] = useState(filters.stock_status || '');

    // Delete/Archive confirmation state
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [productToDelete, setProductToDelete] = useState<Product | null>(null);
    const [deleting, setDeleting] = useState(false);
    const [actionType, setActionType] = useState<'archive' | 'delete'>('archive');
    const [isFiltering, setIsFiltering] = useState(false);
    const [viewMode, setViewMode] = useState<'grid' | 'list' | 'table'>('grid');

    const tRoute = useTenantRoute();

    // Listen for Inertia navigation events
    useEffect(() => {
        const startHandler = () => setIsFiltering(true);
        const finishHandler = () => setIsFiltering(false);

        router.on('start', startHandler);
        router.on('finish', finishHandler);

        return () => {
            // Cleanup not needed for Inertia events but good practice
        };
    }, []);

    const handleArchiveClick = (product: Product) => {
        setProductToDelete(product);
        setActionType('archive');
        setDeleteOpen(true);
    };

    const handleDeleteClick = (product: Product) => {
        setProductToDelete(product);
        setActionType('delete');
        setDeleteOpen(true);
    };

    const confirmArchive = () => {
        if (!productToDelete) return;
        setDeleting(true);
        router.patch(tRoute('inventory.products.archive', { product: productToDelete.id }), {}, {
            onSuccess: () => {
                toast.success('Producto archivado', {
                    description: `"${productToDelete.name}" fue archivado exitosamente.`,
                });
            },
            onFinish: () => {
                setDeleting(false);
                setDeleteOpen(false);
                setProductToDelete(null);
            },
        });
    };

    const confirmDelete = () => {
        if (!productToDelete) return;
        setDeleting(true);
        router.delete(tRoute('inventory.products.destroy', { product: productToDelete.id }), {
            onSuccess: () => {
                toast.success('Producto eliminado', {
                    description: `"${productToDelete.name}" fue eliminado permanentemente.`,
                });
            },
            onFinish: () => {
                setDeleting(false);
                setDeleteOpen(false);
                setProductToDelete(null);
            },
        });
    };

    const handleFilter = () => {
        router.get(tRoute('inventory.index'), {
            search: search || undefined,
            category: category || undefined,
            supplier: supplier || undefined,
            stock_status: stockStatus || undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleStatClick = (status: string) => {
        setStockStatus(status);
        router.get(tRoute('inventory.index'), {
            stock_status: status || undefined,
        });
    };

    const formatPrice = (price: number) => {
        return '$' + price.toLocaleString('es-CL');
    };

    const getStockDot = (product: Product) => {
        if (product.stock <= 0) {
            return <span className="w-3 h-3 rounded-full bg-red-500 border-2 border-white shadow" title="Sin stock" />;
        }
        if (product.stock <= product.min_stock) {
            return <span className="w-3 h-3 rounded-full bg-amber-500 border-2 border-white shadow" title="Stock bajo" />;
        }
        return <span className="w-3 h-3 rounded-full bg-emerald-500 border-2 border-white shadow" title="Stock OK" />;
    };

    const getStockBadge = (product: Product) => {
        if (product.stock <= 0) {
            return <span className="px-2 py-0.5 text-xs font-medium rounded bg-red-100 text-red-800">Stock: {product.stock}</span>;
        }
        if (product.stock <= product.min_stock) {
            return <span className="px-2 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-800">Stock: {product.stock}</span>;
        }
        return <span className="px-2 py-0.5 text-xs font-medium rounded bg-green-100 text-green-800">Stock: {product.stock}</span>;
    };

    // Stat cards (sin STOCK BAJO y SIN STOCK porque están en sidebar)
    const statCards = [
        { label: 'PRODUCTOS', value: stats.total, onClick: () => handleStatClick(''), active: !stockStatus },
        { label: 'SIN IMÁGENES', value: stats.withoutImage, onClick: () => handleStatClick('without_image'), active: stockStatus === 'without_image' },
        { label: 'SIN PROVEEDOR', value: stats.withoutSupplier, onClick: () => handleStatClick('without_supplier'), active: stockStatus === 'without_supplier' },
        { label: 'STOCK NEGATIVO', value: stats.negativeStock, color: 'text-red-600' },
        { label: 'ARCHIVADOS', value: stats.archived, onClick: () => handleStatClick('archived'), active: stockStatus === 'archived', icon: '🗑️' },
    ];

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        📦 Inventario
                    </h2>
                </div>
            }
        >
            <Head title="Inventario" />

            <div className="py-6">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 flex gap-5">
                    <div className="w-full lg:w-64 flex-shrink-0 ">
                        <div className="bg-glass rounded-3xl shadow-panel p-[1.5rem] space-y-4">
                            {/* Categories */}
                            <Collapsible defaultOpen className="border-b pb-5">
                                <CollapsibleTrigger className="w-full text-[1.1rem] font-bold gap-3 text-[#334155] cursor-pointer hover:bg-[#ececec] p-2 rounded-lg flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="currentColor" className="icon icon-tabler icons-tabler-filled icon-tabler-tag"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M11.172 2a3 3 0 0 1 2.121 .879l7.71 7.71a3.41 3.41 0 0 1 0 4.822l-5.592 5.592a3.41 3.41 0 0 1 -4.822 0l-7.71 -7.71a3 3 0 0 1 -.879 -2.121v-5.172a4 4 0 0 1 4 -4zm-3.672 3.5a2 2 0 0 0 -1.995 1.85l-.005 .15a2 2 0 1 0 2 -2" /></svg>
                                    Categorías
                                    <ChevronDown className="ml-auto h-5 w-5 transition-transform duration-200 [[data-state=open]>&]:rotate-180" />
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <ScrollArea className="h-48 mt-2">
                                        <div className="space-y-1 pr-3">
                                            <button
                                                onClick={() => { setCategory(''); handleFilter(); }}
                                                className={`block w-full cursor-pointer text-left px-2 py-1 rounded text-sm ${
                                                    !category ? 'bg-primary text-white font-bold' : 'hover:bg-gray-100 text-[#475569]'
                                                }`}
                                            >
                                                Todas
                                            </button>
                                            {categories.map((cat) => (
                                                <button
                                                    key={cat.id}
                                                    onClick={() => { setCategory(cat.id.toString()); handleFilter(); }}
                                                    className={`flex w-full cursor-pointer items-center justify-between px-2 py-1.5 rounded text-sm ${
                                                        category === cat.id.toString()
                                                            ? 'bg-primary font-bold text-white'
                                                            : 'hover:bg-gray-100 text-[#475569]'
                                                    } ${cat.products_count === 0 ? 'opacity-40' : ''}`}
                                                >
                                                    <span className="truncate">{cat.name}</span>
                                                    <span className={`text-xs ml-2 px-1.5 py-0.5 rounded-full ${
                                                        category === cat.id.toString()
                                                            ? 'bg-white/20 text-white'
                                                            : 'bg-gray-200 text-gray-600'
                                                    }`}>
                                                        {cat.products_count}
                                                    </span>
                                                </button>
                                            ))}
                                        </div>
                                    </ScrollArea>
                                </CollapsibleContent>
                            </Collapsible>

                            {/* Suppliers */}
                            <Collapsible defaultOpen>
                                <CollapsibleTrigger className="w-full text-[1.1rem] font-bold gap-3 text-[#334155] cursor-pointer hover:bg-[#ececec] p-2 rounded-lg flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="currentColor" className="icon icon-tabler icons-tabler-filled icon-tabler-building-store"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M21 6.998v12.002a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-12.002h18zm-6 2.002h-6v6h6v-6zm-2 2v2h-2v-2h2z"/><path d="M3.293 6.929l1.707 -2.929h14l1.707 2.929a1 1 0 0 1 -.707 1.571h-16a1 1 0 0 1 -.707 -1.571z"/></svg>
                                    Proveedores
                                    <ChevronDown className="ml-auto h-5 w-5 transition-transform duration-200 [[data-state=open]>&]:rotate-180" />
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <ScrollArea className="h-48 mt-2">
                                        <div className="space-y-1 pr-3">
                                            <button
                                                onClick={() => { setSupplier(''); handleFilter(); }}
                                                className={`block w-full cursor-pointer text-left px-2 py-1 rounded text-sm ${
                                                    !supplier ? 'bg-primary text-white font-bold' : 'hover:bg-gray-100 text-[#475569]'
                                                }`}
                                            >
                                                Todos
                                            </button>
                                            {suppliers.map((sup) => (
                                                <button
                                                    key={sup.id}
                                                    onClick={() => { setSupplier(sup.id.toString()); handleFilter(); }}
                                                    className={`flex w-full cursor-pointer items-center justify-between px-2 py-1.5 rounded text-sm ${
                                                        supplier === sup.id.toString()
                                                            ? 'bg-primary font-bold text-white'
                                                            : 'hover:bg-gray-100 text-[#475569]'
                                                    } ${sup.products_count === 0 ? 'opacity-40' : ''}`}
                                                >
                                                    <span className="truncate">{sup.name}</span>
                                                    <span className={`text-xs ml-2 px-1.5 py-0.5 rounded-full ${
                                                        supplier === sup.id.toString()
                                                            ? 'bg-white/20 text-white'
                                                            : 'bg-gray-200 text-gray-600'
                                                    }`}>
                                                        {sup.products_count}
                                                    </span>
                                                </button>
                                            ))}
                                        </div>
                                    </ScrollArea>
                                </CollapsibleContent>
                            </Collapsible>

                            {/* Stock Status */}
                            <Collapsible defaultOpen>
                                <CollapsibleTrigger className="w-full text-[1.1rem] font-bold gap-3 text-[#334155] cursor-pointer hover:bg-[#ececec] p-2 rounded-lg flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                                    Stock
                                    <ChevronDown className="ml-auto h-5 w-5 transition-transform duration-200 [[data-state=open]>&]:rotate-180" />
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <div className="space-y-1 mt-2">
                                        <button
                                            onClick={() => handleStatClick('')}
                                            className={`flex w-full cursor-pointer items-center gap-2 px-2 py-1.5 rounded text-sm ${
                                                !stockStatus ? 'bg-primary font-bold text-white' : 'hover:bg-gray-100 text-[#475569]'
                                            }`}
                                        >
                                            <span className="w-2.5 h-2.5 rounded-full bg-emerald-500" />
                                            <span>Todos</span>
                                            <span className={`text-xs ml-auto px-1.5 py-0.5 rounded-full ${
                                                !stockStatus ? 'bg-white/20 text-white' : 'bg-gray-200 text-gray-600'
                                            }`}>{stats.total}</span>
                                        </button>
                                        <button
                                            onClick={() => handleStatClick('low')}
                                            className={`flex w-full cursor-pointer items-center gap-2 px-2 py-1.5 rounded text-sm ${
                                                stockStatus === 'low' ? 'bg-primary font-bold text-white' : 'hover:bg-gray-100 text-[#475569]'
                                            }`}
                                        >
                                            <span className="w-2.5 h-2.5 rounded-full bg-amber-500" />
                                            <span>Stock Bajo</span>
                                            <span className={`text-xs ml-auto px-1.5 py-0.5 rounded-full ${
                                                stockStatus === 'low' ? 'bg-white/20 text-white' : 'bg-gray-200 text-gray-600'
                                            }`}>{stats.lowStock}</span>
                                        </button>
                                        <button
                                            onClick={() => handleStatClick('out')}
                                            className={`flex w-full cursor-pointer items-center gap-2 px-2 py-1.5 rounded text-sm ${
                                                stockStatus === 'out' ? 'bg-primary font-bold text-white' : 'hover:bg-gray-100 text-[#475569]'
                                            }`}
                                        >
                                            <span className="w-2.5 h-2.5 rounded-full bg-red-500" />
                                            <span>Sin Stock</span>
                                            <span className={`text-xs ml-auto px-1.5 py-0.5 rounded-full ${
                                                stockStatus === 'out' ? 'bg-white/20 text-white' : 'bg-gray-200 text-gray-600'
                                            }`}>{stats.outOfStock}</span>
                                        </button>
                                        <button
                                            onClick={() => handleStatClick('archived')}
                                            className={`flex w-full cursor-pointer items-center gap-2 px-2 py-1.5 rounded text-sm ${
                                                stockStatus === 'archived' ? 'bg-primary font-bold text-white' : 'hover:bg-gray-100 text-[#475569]'
                                            }`}
                                        >
                                            <span className="w-2.5 h-2.5 rounded-full bg-gray-400" />
                                            <span>Archivados</span>
                                            <span className={`text-xs ml-auto px-1.5 py-0.5 rounded-full ${
                                                stockStatus === 'archived' ? 'bg-white/20 text-white' : 'bg-gray-200 text-gray-600'
                                            }`}>{stats.archived}</span>
                                        </button>
                                    </div>
                                </CollapsibleContent>
                            </Collapsible>
                        </div>
                    </div>
                    <div className="w-full">
                        {/* Stat Cards */}
                        <div className="flex justify-between gap-4 mb-6">
                            {statCards.map((stat, index) => (
                                <button
                                    key={index}
                                    onClick={stat.onClick}
                                    className={`bg-white rounded-3xl shadow-panel p-4 text-center hover:shadow-md transition flex-1 ${
                                        stat.active ? 'ring-2 ring-blue-500' : ''
                                    }`}
                                >
                                    <div className="text-xs text-gray-500 font-medium mb-1">
                                        {stat.label}
                                    </div>
                                    <div className={`text-3xl font-nunito font-bold ${stat.color || 'text-gray-900'}`}>
                                        {stat.value.toLocaleString()}
                                    </div>
                                </button>
                            ))}
                        </div>

                        <div className="flex flex-col lg:flex-row gap-6">

                            {/* Main Content */}
                            <div className="flex-1">
                                {/* Search and Actions */}
                                <div className="bg-white rounded-lg shadow p-4 mb-4">
                                    <div className="flex flex-col sm:flex-row gap-3">
                                        <div className="flex-1">
                                            <input
                                                type="text"
                                                placeholder="🔍 Buscar productos..."
                                                value={search}
                                                onChange={(e) => setSearch(e.target.value)}
                                                onKeyDown={(e) => e.key === 'Enter' && handleFilter()}
                                                className="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            />
                                        </div>
                                        <div className="flex gap-2">
                                            <Link
                                                href={tRoute('inventory.products.create')}
                                                className="inline-flex items-center px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-blue-700"
                                            >
                                                + Nuevo Producto
                                            </Link>
                                            <Link
                                                href={tRoute('inventory.suppliers.create')}
                                                className="inline-flex items-center px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-blue-700"
                                            >
                                                + Nuevo Proveedor
                                            </Link>

                                            <Link
                                                href={tRoute('inventory.categories.index')}
                                                className="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50"
                                            >
                                                + Nueva Categoría
                                            </Link>
                                            <Link
                                                href={tRoute('inventory.suppliers.index')}
                                                className="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50"
                                            >
                                                + Nuevo Proveedor
                                            </Link>

                                            {/* View Mode Toggle */}
                                            <div className="flex items-center border border-gray-200 rounded-lg overflow-hidden">
                                                <button
                                                    onClick={() => setViewMode('grid')}
                                                    className={`p-2 ${viewMode === 'grid' ? 'bg-primary text-white' : 'bg-white text-gray-500 hover:bg-gray-50'}`}
                                                    title="Vista cuadrícula"
                                                >
                                                    <LayoutGrid className="w-4 h-4" />
                                                </button>
                                                <button
                                                    onClick={() => setViewMode('list')}
                                                    className={`p-2 border-x border-gray-200 ${viewMode === 'list' ? 'bg-primary text-white' : 'bg-white text-gray-500 hover:bg-gray-50'}`}
                                                    title="Vista lista"
                                                >
                                                    <List className="w-4 h-4" />
                                                </button>
                                                <button
                                                    onClick={() => setViewMode('table')}
                                                    className={`p-2 ${viewMode === 'table' ? 'bg-primary text-white' : 'bg-white text-gray-500 hover:bg-gray-50'}`}
                                                    title="Vista tabla"
                                                >
                                                    <Table className="w-4 h-4" />
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {/* Products */}
                                {isFiltering ? (
                                    <ProductGridSkeleton count={8} />
                                ) : (
                                <>
                                    {/* Grid View */}
                                    {viewMode === 'grid' && (
                                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                                        {products.data.map((product) => (
                                            <div key={product.id} className="bg-white rounded-lg shadow overflow-hidden hover:shadow-md transition group">
                                                <div className="aspect-square bg-gray-100 relative">
                                                    {product.image ? (
                                                        <img src={`/storage/${product.image}`} alt={product.name} className="w-full h-full object-cover" />
                                                    ) : (
                                                        <div className="w-full h-full flex items-center justify-center text-gray-400">
                                                            <svg className="w-16 h-16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                            </svg>
                                                        </div>
                                                    )}
                                                    {/* Stock Dot - top left */}
                                                    <div className="absolute top-2 left-2">
                                                        {getStockDot(product)}
                                                    </div>
                                                    {product.stock <= 0 && (
                                                        <div className="absolute top-2 right-2">
                                                            <span className="bg-red-500 text-white text-xs px-2 py-1 rounded">Sin Stock</span>
                                                        </div>
                                                    )}
                                                </div>
                                                <div className="p-4">
                                                    <h3 className="font-medium text-gray-900 truncate">{product.name}</h3>
                                                    <p className="text-lg font-bold text-primary">{formatPrice(product.price)}</p>
                                                    <div className="mt-2 text-sm text-gray-500">
                                                        <p>SKU: {product.sku || 'N/A'}</p>
                                                        <p>Costo: {formatPrice(product.cost)}</p>
                                                    </div>
                                                    <div className="mt-2">{getStockBadge(product)}</div>
                                                    <div className="mt-3 flex items-center justify-center gap-1 border-t pt-3">
                                                        {stockStatus === 'archived' ? (
                                                            <>
                                                                <button onClick={() => { router.patch(tRoute('inventory.products.restore', { product: product.id }), {}, { onSuccess: () => toast.success('Producto restaurado', { description: `"${product.name}" fue restaurado.` }) }); }} className="p-2 rounded-lg text-green-600 hover:bg-green-50 transition" title="Restaurar"><RotateCcw className="w-4 h-4" /></button>
                                                                <button onClick={() => handleDeleteClick(product)} className="p-2 rounded-lg text-red-500 hover:bg-red-50 transition" title="Eliminar"><Trash2 className="w-4 h-4" /></button>
                                                            </>
                                                        ) : (
                                                            <>
                                                                <Link href={tRoute('inventory.products.edit', { product: product.id })} className="p-2 rounded-lg text-blue-600 hover:bg-blue-50 transition" title="Editar"><Pencil className="w-4 h-4" /></Link>
                                                                <button onClick={() => { router.post(tRoute('inventory.products.duplicate', { product: product.id }), {}, { onSuccess: () => toast.success('Producto duplicado', { description: `Se creó una copia de "${product.name}".` }) }); }} className="p-2 rounded-lg text-purple-500 hover:bg-purple-50 transition" title="Duplicar"><Copy className="w-4 h-4" /></button>
                                                                <button onClick={() => handleArchiveClick(product)} className="p-2 rounded-lg text-amber-500 hover:bg-amber-50 transition" title="Archivar"><Archive className="w-4 h-4" /></button>
                                                                <button onClick={() => handleDeleteClick(product)} className="p-2 rounded-lg text-red-500 hover:bg-red-50 transition" title="Eliminar"><Trash2 className="w-4 h-4" /></button>
                                                            </>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                    )}

                                    {/* List View */}
                                    {viewMode === 'list' && (
                                    <div className="space-y-3">
                                        {products.data.map((product) => (
                                            <div key={product.id} className="bg-white rounded-lg shadow p-4 hover:shadow-md transition flex items-center gap-4">
                                                <div className="w-16 h-16 bg-gray-100 rounded-lg overflow-hidden flex-shrink-0">
                                                    {product.image ? (
                                                        <img src={`/storage/${product.image}`} alt={product.name} className="w-full h-full object-cover" />
                                                    ) : (
                                                        <div className="w-full h-full flex items-center justify-center text-gray-400">
                                                            <svg className="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                            </svg>
                                                        </div>
                                                    )}
                                                </div>
                                                <div className="flex-1 min-w-0">
                                                    <h3 className="font-medium text-gray-900 truncate">{product.name}</h3>
                                                    <p className="text-sm text-gray-500">{product.category?.name || 'Sin categoría'} • SKU: {product.sku || 'N/A'}</p>
                                                </div>
                                                <div className="text-right hidden sm:block">
                                                    <p className="font-bold text-primary">{formatPrice(product.price)}</p>
                                                    <p className="text-sm text-gray-500">Costo: {formatPrice(product.cost)}</p>
                                                </div>
                                                <div className="hidden md:block">{getStockBadge(product)}</div>
                                                <div className="flex items-center gap-1">
                                                    <Link href={tRoute('inventory.products.edit', { product: product.id })} className="p-2 rounded-lg text-blue-600 hover:bg-blue-50 transition" title="Editar"><Pencil className="w-4 h-4" /></Link>
                                                    <button onClick={() => handleArchiveClick(product)} className="p-2 rounded-lg text-amber-500 hover:bg-amber-50 transition" title="Archivar"><Archive className="w-4 h-4" /></button>
                                                    <button onClick={() => handleDeleteClick(product)} className="p-2 rounded-lg text-red-500 hover:bg-red-50 transition" title="Eliminar"><Trash2 className="w-4 h-4" /></button>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                    )}

                                    {/* Table View */}
                                    {viewMode === 'table' && (
                                    <div className="bg-white rounded-lg shadow overflow-hidden">
                                        <table className="min-w-full divide-y divide-gray-200">
                                            <thead className="bg-gray-50">
                                                <tr>
                                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Producto</th>
                                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">SKU</th>
                                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden md:table-cell">Categoría</th>
                                                    <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Precio</th>
                                                    <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Stock</th>
                                                    <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody className="bg-white divide-y divide-gray-200">
                                                {products.data.map((product) => (
                                                    <tr key={product.id} className="hover:bg-gray-50">
                                                        <td className="px-4 py-3 whitespace-nowrap">
                                                            <div className="flex items-center gap-3">
                                                                <div className="w-10 h-10 bg-gray-100 rounded overflow-hidden flex-shrink-0">
                                                                    {product.image ? (
                                                                        <img src={`/storage/${product.image}`} alt={product.name} className="w-full h-full object-cover" />
                                                                    ) : (
                                                                        <div className="w-full h-full flex items-center justify-center text-gray-300">
                                                                            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                                                        </div>
                                                                    )}
                                                                </div>
                                                                <div className="font-medium text-gray-900 truncate max-w-[200px]">{product.name}</div>
                                                            </div>
                                                        </td>
                                                        <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500 hidden sm:table-cell">{product.sku || '-'}</td>
                                                        <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500 hidden md:table-cell">{product.category?.name || '-'}</td>
                                                        <td className="px-4 py-3 whitespace-nowrap text-right font-medium text-primary">{formatPrice(product.price)}</td>
                                                        <td className="px-4 py-3 whitespace-nowrap text-center">{getStockBadge(product)}</td>
                                                        <td className="px-4 py-3 whitespace-nowrap text-center">
                                                            <div className="flex items-center justify-center gap-1">
                                                                <Link href={tRoute('inventory.products.edit', { product: product.id })} className="p-1.5 rounded text-blue-600 hover:bg-blue-50 transition" title="Editar"><Pencil className="w-4 h-4" /></Link>
                                                                <button onClick={() => handleArchiveClick(product)} className="p-1.5 rounded text-amber-500 hover:bg-amber-50 transition" title="Archivar"><Archive className="w-4 h-4" /></button>
                                                                <button onClick={() => handleDeleteClick(product)} className="p-1.5 rounded text-red-500 hover:bg-red-50 transition" title="Eliminar"><Trash2 className="w-4 h-4" /></button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                    )}

                                    {products.data.length === 0 && (
                                        <div className="bg-white rounded-lg shadow p-12 text-center">
                                            <div className="text-6xl mb-4">📦</div>
                                            <h3 className="text-lg font-medium text-gray-900 mb-2">No hay productos</h3>
                                            <p className="text-gray-500 mb-4">Comienza agregando tu primer producto al inventario.</p>
                                            <Link href={tRoute('inventory.products.create')} className="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">+ Nuevo Producto</Link>
                                        </div>
                                    )}
                                </>
                                )}

                                {/* Pagination - show if more than 5 products */}
                                {products.total > 5 && (
                                    <div className="mt-6 flex flex-col sm:flex-row items-center justify-between gap-4 bg-white rounded-xl shadow p-4">
                                        {/* Info */}
                                        <p className="text-sm text-gray-500">
                                            Mostrando <span className="font-medium text-gray-900">{((products.current_page - 1) * products.per_page) + 1}</span> a{' '}
                                            <span className="font-medium text-gray-900">{Math.min(products.current_page * products.per_page, products.total)}</span> de{' '}
                                            <span className="font-medium text-gray-900">{products.total}</span> productos
                                        </p>

                                        {/* Page Controls */}
                                        <div className="flex items-center gap-1">
                                            {/* First Page */}
                                            <Link
                                                href={products.links[0]?.url || '#'}
                                                className={`p-2 rounded-lg transition ${
                                                    products.current_page === 1
                                                        ? 'text-gray-300 cursor-not-allowed'
                                                        : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700'
                                                }`}
                                                title="Primera página"
                                            >
                                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                    <path strokeLinecap="round" strokeLinejoin="round" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                                                </svg>
                                            </Link>

                                            {/* Previous */}
                                            <Link
                                                href={products.links[0]?.url || '#'}
                                                className={`p-2 rounded-lg transition ${
                                                    products.current_page === 1
                                                        ? 'text-gray-300 cursor-not-allowed'
                                                        : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700'
                                                }`}
                                                title="Anterior"
                                            >
                                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                    <path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" />
                                                </svg>
                                            </Link>

                                            {/* Page Numbers - Max 9 */}
                                            <div className="flex items-center gap-1">
                                                {products.links.slice(1, -1).map((link, index) => {
                                                    const pageNum = index + 1;
                                                    const totalPages = products.last_page;
                                                    const current = products.current_page;

                                                    // Smart pagination: show max 9 pages
                                                    // Always show: first, last, current, and 2 neighbors on each side
                                                    let showPage = false;
                                                    let showLeftEllipsis = false;
                                                    let showRightEllipsis = false;

                                                    if (totalPages <= 9) {
                                                        // Show all pages if 9 or less
                                                        showPage = true;
                                                    } else {
                                                        // Show first and last always
                                                        if (pageNum === 1 || pageNum === totalPages) {
                                                            showPage = true;
                                                        }
                                                        // Show current and 2 neighbors
                                                        else if (Math.abs(pageNum - current) <= 2) {
                                                            showPage = true;
                                                        }
                                                        // Show ellipsis indicators
                                                        else if (pageNum === 2 && current > 4) {
                                                            showLeftEllipsis = true;
                                                        }
                                                        else if (pageNum === totalPages - 1 && current < totalPages - 3) {
                                                            showRightEllipsis = true;
                                                        }
                                                    }

                                                    if (showLeftEllipsis || showRightEllipsis) {
                                                        return <span key={index} className="px-2 text-gray-400">...</span>;
                                                    }
                                                    if (!showPage) return null;

                                                    return (
                                                        <Link
                                                            key={index}
                                                            href={link.url || '#'}
                                                            className={`min-w-[36px] h-9 flex items-center justify-center rounded-lg text-sm font-medium transition ${
                                                                link.active
                                                                    ? 'bg-primary text-white shadow-md'
                                                                    : 'text-gray-600 hover:bg-gray-100'
                                                            }`}
                                                        >
                                                            {pageNum}
                                                        </Link>
                                                    );
                                                })}
                                            </div>

                                            {/* Next */}
                                            <Link
                                                href={products.links[products.links.length - 1]?.url || '#'}
                                                className={`p-2 rounded-lg transition ${
                                                    products.current_page === products.last_page
                                                        ? 'text-gray-300 cursor-not-allowed'
                                                        : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700'
                                                }`}
                                                title="Siguiente"
                                            >
                                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                    <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
                                                </svg>
                                            </Link>

                                            {/* Last Page */}
                                            <Link
                                                href={products.links[products.links.length - 1]?.url || '#'}
                                                className={`p-2 rounded-lg transition ${
                                                    products.current_page === products.last_page
                                                        ? 'text-gray-300 cursor-not-allowed'
                                                        : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700'
                                                }`}
                                                title="Última página"
                                            >
                                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                    <path strokeLinecap="round" strokeLinejoin="round" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                                                </svg>
                                            </Link>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Delete/Archive Confirmation Dialog */}
            <ConfirmDialog
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
                title={actionType === 'archive' ? '¿Archivar producto?' : '¿Eliminar producto?'}
                description={
                    actionType === 'archive'
                        ? `¿Estás seguro de archivar "${productToDelete?.name}"? Podrás restaurarlo más tarde desde la sección de archivados.`
                        : `¿Estás seguro de eliminar "${productToDelete?.name}" permanentemente? Esta acción no se puede deshacer.`
                }
                confirmLabel={actionType === 'archive' ? 'Archivar' : 'Eliminar'}
                cancelLabel="Cancelar"
                onConfirm={actionType === 'archive' ? confirmArchive : confirmDelete}
                variant={actionType === 'archive' ? 'warning' : 'danger'}
                loading={deleting}
            />
        </AuthenticatedLayout>
    );
}
