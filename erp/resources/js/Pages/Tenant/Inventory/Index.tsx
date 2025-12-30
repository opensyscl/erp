import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { useTenantRoute } from '@/Hooks/useTenantRoute';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { ChevronDown, Pencil, Copy, Archive, Trash2, RotateCcw, LayoutGrid, List, Table, Search, Plus, Package, Truck, Tags, SlidersHorizontal } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { ProductGridSkeleton } from '@/components/skeletons/InventorySkeleton';
import { toast } from 'sonner';
import { ProductItemIcon, ImageOffIcon, UserOffIcon, AlertTriangleIcon, ArchiveIcon } from '@/components/Icons';
import ProductFormDrawer from '@/components/Inventory/ProductFormDrawer';

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
    const [sortBy, setSortBy] = useState(filters.sort ? `${filters.sort}_${filters.dir}` : 'updated_at_desc');

    // Delete/Archive confirmation state
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [productToDelete, setProductToDelete] = useState<Product | null>(null);
    const [deleting, setDeleting] = useState(false);
    const [actionType, setActionType] = useState<'archive' | 'delete'>('archive');
    const [isFiltering, setIsFiltering] = useState(false);

    // ViewMode with localStorage persistence
    const [viewMode, setViewModeState] = useState<'grid' | 'list' | 'table'>(() => {
        if (typeof window !== 'undefined') {
            const saved = localStorage.getItem('inventory-view-mode');
            if (saved && ['grid', 'list', 'table'].includes(saved)) {
                return saved as 'grid' | 'list' | 'table';
            }
        }
        return 'grid';
    });

    const setViewMode = (mode: 'grid' | 'list' | 'table') => {
        setViewModeState(mode);
        localStorage.setItem('inventory-view-mode', mode);
    };

    const [productDrawerOpen, setProductDrawerOpen] = useState(false);

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
        const [sort, dir] = sortBy.split('_');
        router.get(tRoute('inventory.index'), {
            search: search || undefined,
            category: category || undefined,
            supplier: supplier || undefined,
            stock_status: stockStatus || undefined,
            sort: sort || undefined,
            dir: dir || undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleSortChange = (value: string) => {
        setSortBy(value);
        const [sort, dir] = value.split('_');
        router.get(tRoute('inventory.index'), {
            search: search || undefined,
            category: category || undefined,
            supplier: supplier || undefined,
            stock_status: stockStatus || undefined,
            sort,
            dir,
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
            return <span className="w-4 h-4 rounded-full bg-danger border-2  block border-white shadow" title="Sin stock" />;
        }
        if (product.stock <= product.min_stock) {
            return <span className="w-4 h-4 rounded-full bg-warning border-2 block border-white shadow" title="Stock bajo" />;
        }
        return <span className="w-4 h-4 rounded-full bg-success border-2  block border-white shadow" title="Stock OK" />;
    };

    const getStockBadge = (product: Product) => {
        if (product.stock <= 0) {
            return <span className="px-2 py-0.5 text-xs font-bold rounded-3xl bg-red-100 text-danger">Stock: {product.stock}</span>;
        }
        if (product.stock <= product.min_stock) {
            return <span className="px-2 py-0.5 text-xs font-bold rounded-3xl bg-yellow-100 text-warning">Stock: {product.stock}</span>;
        }
        return <span className="px-2 py-0.5 text-xs font-bold rounded-3xl bg-green-100 text-success">Stock: {product.stock}</span>;
    };

    const statCards = [
        { label: 'Productos', value: stats.total, onClick: () => handleStatClick(''), active: !stockStatus, icon: <ProductItemIcon size={23} /> },
        { label: 'Sin imágenes', value: stats.withoutImage, onClick: () => handleStatClick('without_image'), active: stockStatus === 'without_image', icon: <ImageOffIcon size={23} /> },
        { label: 'Sin proveedor', value: stats.withoutSupplier, onClick: () => handleStatClick('without_supplier'), active: stockStatus === 'without_supplier', icon: <UserOffIcon size={23} /> },
        { label: 'Stock negativo', value: stats.negativeStock, onClick: () => handleStatClick('negative'), active: stockStatus === 'negative', color: 'text-red-600', icon: <AlertTriangleIcon size={23} /> },
        { label: 'Archivados', value: stats.archived, onClick: () => handleStatClick('archived'), active: stockStatus === 'archived', icon: <ArchiveIcon size={23} /> },
    ];

    // Navigation tabs for this section
    const sectionTabs = [
        { label: 'Inventario', href: tRoute('inventory.index'), active: true },
        { label: 'Análisis de Inventario', href: tRoute('inventory.analysis'), active: false },
        { label: 'Conteo Físico', href: '#', disabled: true },
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
            sectionTabs={sectionTabs}
        >
            <Head title="Inventario" />

            <div className="py-6">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-3 flex gap-5">
                    <div className="w-full lg:w-64 shrink-0 ">
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
                                                className={`block w-full cursor-pointer text-left px-3 py-2 rounded-3xl text-xs ${
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
                                                className={`block w-full cursor-pointer text-left px-3 py-2 rounded-3xl text-xs ${
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
                                                    <span className={`text-xs ml-2 px-2 py-1 rounded-full ${
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
                                            <span className="w-2.5 h-2.5 rounded-full bg-success" />
                                            <span>Todos</span>
                                            <span className={`text-xs ml-auto px-1.5 py-0.5 rounded-full ${
                                                !stockStatus ? 'bg-white/20 text-white' : 'bg-gray-200 text-gray-600'
                                            }`}>{stats.total}</span>
                                        </button>
                                        <button
                                            onClick={() => handleStatClick('low')}
                                            className={`flex w-full cursor-pointer items-center gap-2 px-3 py-2 rounded-3xl text-xs ${
                                                stockStatus === 'low' ? 'bg-primary font-bold text-white' : 'hover:bg-gray-100 text-[#475569]'
                                            }`}
                                        >
                                            <span className="w-2.5 h-2.5 rounded-full bg-warning" />
                                            <span>Stock Bajo</span>
                                            <span className={`text-xs ml-auto px-1.5 py-0.5 rounded-full ${
                                                stockStatus === 'low' ? 'bg-white/20 text-white' : 'bg-gray-200 text-gray-600'
                                            }`}>{stats.lowStock}</span>
                                        </button>
                                        <button
                                            onClick={() => handleStatClick('out')}
                                            className={`flex w-full cursor-pointer items-center gap-2 px-3 py-2 rounded-3xl text-xs ${
                                                stockStatus === 'out' ? 'bg-primary font-bold text-white' : 'hover:bg-gray-100 text-[#475569]'
                                            }`}
                                        >
                                            <span className="w-2.5 h-2.5 rounded-full bg-danger" />
                                            <span>Sin Stock</span>
                                            <span className={`text-xs ml-auto px-1.5 py-0.5 rounded-full ${
                                                stockStatus === 'out' ? 'bg-white/20 text-white' : 'bg-gray-200 text-gray-600'
                                            }`}>{stats.outOfStock}</span>
                                        </button>
                                        <button
                                            onClick={() => handleStatClick('archived')}
                                            className={`flex w-full cursor-pointer items-center gap-2 px-3 py-2 rounded-3xl text-xs ${
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

                        <div className="flex justify-between gap-4 mb-6">
                            {statCards.map((stat, index) => (
                                <button
                                    key={index}
                                    onClick={stat.onClick}
                                    className={`grow w-full cursor-pointer ring-2 ring-transparent transition-all  duration-600 ease-spring-snappy hover:ring-primary/70 p-0 flex flex-col background-image-decoration justify-between shadow-panel bg-white rounded-3xl gap-6 h-full bg-cover  bg-no-repeat channel-stats-bg ${
                                        stat.active ? ' ring-primary' : ''
                                    }`}
                                >
                                    <div className="w-10 rounded-xl mt-4 ms-5 ">
                                        {stat.icon && <span className="text-gray-400">{stat.icon}</span>}
                                    </div>
                                    <div className="flex flex-col gap-1 pb-4 px-5">
                                         <div className={`text-3xl font-semibold text-mono ${stat.color || 'text-gray-900'}`}>
                                            {stat.value.toLocaleString()}
                                        </div>
                                        <span className="text-sm  text-[#334155] font-bold">{stat.label}</span>
                                    </div>

                                </button>
                            ))}
                        </div>

                        <div className="flex flex-col lg:flex-row gap-6">

                            <div className="flex-1">
                                <div className="bg-white rounded-2xl shadow-panel border border-gray-100 p-4 mb-6">
                                    <div className="flex flex-col lg:flex-row gap-4">
                                        {/* Search Input with Icon */}
                                        <div className="flex-1 relative">
                                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                                            <input
                                                type="text"
                                                placeholder="Buscar por nombre, SKU o código de barras..."
                                                value={search}
                                                onChange={(e) => setSearch(e.target.value)}
                                                onKeyDown={(e) => e.key === 'Enter' && handleFilter()}
                                                className="w-full pl-10 pr-4 py-2.5 rounded-xl border-gray-200 bg-gray-50/50 focus:bg-white focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all text-sm"
                                            />
                                        </div>

                                        {/* Controls Group */}
                                        <div className="flex  items-center gap-3">
                                            {/* Sort Select */}
                                            <Select  value={sortBy} onValueChange={handleSortChange}>
                                                <SelectTrigger className="w-auto rounded-xl border-gray-200 bg-gray-50/50 focus:bg-white">
                                                    <div className="flex items-center gap-2">
                                                        <SlidersHorizontal className="w-4 h-4 text-gray-400" />
                                                        <SelectValue placeholder="Ordenar por" />
                                                    </div>
                                                </SelectTrigger>
                                                <SelectContent className="rounded-3xl bg-glass">
                                                    <SelectItem value="updated_at_desc">Reciente primero</SelectItem>
                                                    <SelectItem value="name_asc">Nombre (A-Z)</SelectItem>
                                                    <SelectItem value="name_desc">Nombre (Z-A)</SelectItem>
                                                    <SelectItem value="stock_desc">Mayor stock</SelectItem>
                                                    <SelectItem value="stock_asc">Menor stock</SelectItem>
                                                    <SelectItem value="price_desc">Mayor precio</SelectItem>
                                                    <SelectItem value="price_asc">Menor precio</SelectItem>
                                                    <SelectItem value="created_at_desc">Más nuevo</SelectItem>
                                                    <SelectItem value="created_at_asc">Más antiguo</SelectItem>
                                                </SelectContent>
                                            </Select>

                                            {/* Divider */}
                                            <div className="hidden lg:block w-px h-8 bg-gray-200" />

                                            {/* View Mode Toggle - Pill Style */}
                                            <div className="flex items-center p-1 bg-gray-100 rounded-xl">
                                                <button
                                                    onClick={() => setViewMode('grid')}
                                                    className={`p-2 rounded-lg transition-all duration-200 ${
                                                        viewMode === 'grid'
                                                            ? 'bg-white text-primary shadow-sm'
                                                            : 'text-gray-500 hover:text-gray-700'
                                                    }`}
                                                    title="Vista cuadrícula"
                                                >
                                                    <LayoutGrid className="w-4 h-4" />
                                                </button>
                                                <button
                                                    onClick={() => setViewMode('list')}
                                                    className={`p-2 rounded-lg transition-all duration-200 ${
                                                        viewMode === 'list'
                                                            ? 'bg-white text-primary shadow-sm'
                                                            : 'text-gray-500 hover:text-gray-700'
                                                    }`}
                                                    title="Vista lista"
                                                >
                                                    <List className="w-4 h-4" />
                                                </button>
                                                <button
                                                    onClick={() => setViewMode('table')}
                                                    className={`p-2 rounded-lg transition-all duration-200 ${
                                                        viewMode === 'table'
                                                            ? 'bg-white text-primary shadow-sm'
                                                            : 'text-gray-500 hover:text-gray-700'
                                                    }`}
                                                    title="Vista tabla"
                                                >
                                                    <Table className="w-4 h-4" />
                                                </button>
                                            </div>

                                            {/* Divider */}
                                            <div className="hidden lg:block w-px h-8 bg-gray-200" />

                                            {/* Create Dropdown */}
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <button className="inline-flex cursor-pointer items-center gap-2 px-4 py-2.5 bg-primary hover:bg-primary/90 text-white text-sm font-medium rounded-xl transition-colors shadow-sm">
                                                        <Plus className="w-4 h-4" />
                                                        <span>Crear nuevo</span>
                                                        <ChevronDown className="w-4 h-4 opacity-70" />
                                                    </button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end" className="w-52">
                                                    <DropdownMenuItem
                                                        onClick={() => setProductDrawerOpen(true)}
                                                        className="cursor-pointer"
                                                    >
                                                        <Package className="w-4 h-4 mr-2 text-primary" />
                                                        <span>Nuevo Producto</span>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem asChild>
                                                        <Link href={tRoute('inventory.categories.index')} className="cursor-pointer">
                                                            <Tags className="w-4 h-4 mr-2 text-purple" />
                                                            <span>Nueva Categoría</span>
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem asChild>
                                                        <Link href={tRoute('inventory.suppliers.create')} className="cursor-pointer">
                                                            <Truck className="w-4 h-4 mr-2 text-success" />
                                                            <span>Nuevo Proveedor</span>
                                                        </Link>
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </div>
                                    </div>

                                    {/* Active Filters Display */}
                                    {(search || category || supplier || stockStatus) && (
                                        <div className="flex items-center gap-2 mt-4 pt-4 border-t border-gray-100">
                                            <span className="text-xs text-gray-500 font-medium">Filtros activos:</span>
                                            <div className="flex flex-wrap gap-2">
                                                {search && (
                                                    <span className="inline-flex items-center gap-1 px-2.5 py-1 bg-blue-50 text-primary text-xs font-medium rounded-lg">
                                                        <Search className="w-3 h-3" />
                                                        "{search}"
                                                        <button onClick={() => { setSearch(''); handleFilter(); }} className="ml-1 hover:text-primary">×</button>
                                                    </span>
                                                )}
                                                {category && (
                                                    <span className="inline-flex items-center gap-1 px-2.5 py-1 bg-purple-50 text-purple text-xs font-medium rounded-lg">
                                                        <Tags className="w-3 h-3" />
                                                        {categories.find(c => c.id.toString() === category)?.name}
                                                        <button onClick={() => { setCategory(''); handleFilter(); }} className="ml-1 hover:text-purple">×</button>
                                                    </span>
                                                )}
                                                {supplier && (
                                                    <span className="inline-flex items-center gap-1 px-2.5 py-1 bg-green-50 text-success text-xs font-medium rounded-lg">
                                                        <Truck className="w-3 h-3" />
                                                        {suppliers.find(s => s.id.toString() === supplier)?.name}
                                                        <button onClick={() => { setSupplier(''); handleFilter(); }} className="ml-1 hover:text-success">×</button>
                                                    </span>
                                                )}
                                                {stockStatus && (
                                                    <span className="inline-flex items-center gap-1 px-2.5 py-1 bg-amber-50 text-warning text-xs font-medium rounded-lg">
                                                        <Package className="w-3 h-3" />
                                                        {stockStatus === 'low' ? 'Stock bajo' :
                                                         stockStatus === 'out' ? 'Sin stock' :
                                                         stockStatus === 'archived' ? 'Archivados' :
                                                         stockStatus === 'without_image' ? 'Sin imagen' :
                                                         stockStatus === 'without_supplier' ? 'Sin proveedor' :
                                                         stockStatus === 'negative' ? 'Stock negativo' : stockStatus}
                                                        <button onClick={() => { setStockStatus(''); handleFilter(); }} className="ml-1 hover:text-warning cursor-pointer">×</button>
                                                    </span>
                                                )}
                                            </div>
                                            <button
                                                onClick={() => { setSearch(''); setCategory(''); setSupplier(''); setStockStatus(''); handleFilter(); }}
                                                className="ml-auto text-xs text-gray-500 hover:text-gray-700 underline"
                                            >
                                                Limpiar todo
                                            </button>
                                        </div>
                                    )}
                                </div>

                                {/* Products */}
                                {isFiltering ? (
                                    <ProductGridSkeleton count={8} />
                                ) : (
                                <>
                                    {/* Grid View */}
                                    {viewMode === 'grid' && (
                                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                                        {products.data.map((product) => (
                                            <div
                                                key={product.id}
                                                className="group bg-white rounded-2xl border border-gray-100 overflow-hidden hover:shadow-lg hover:border-gray-200 transition-all duration-300"
                                            >
                                                {/* Image Container */}
                                                <div className="relative aspect-square bg-glass border-b p-4">
                                                    {product.image ? (
                                                        <img
                                                            src={`/storage/${product.image}`}
                                                            alt={product.name}
                                                            className="w-full h-full object-contain group-hover:scale-105 transition-transform duration-300"
                                                        />
                                                    ) : (
                                                        <div className="w-full h-full flex items-center justify-center">
                                                            <Package className="w-16 h-16 text-gray-300" />
                                                        </div>
                                                    )}

                                                    {/* Stock Indicator */}
                                                    <div className="absolute top-3 left-3">
                                                        {getStockDot(product)}
                                                    </div>

                                                    {/* Out of Stock Badge */}
                                                    {product.stock <= 0 && (
                                                        <div className="absolute top-3 right-3">
                                                            <span className="bg-red-500/90 backdrop-blur-sm text-white text-[10px] font-bold uppercase tracking-wide px-2 py-1 rounded-md">
                                                                Agotado
                                                            </span>
                                                        </div>
                                                    )}

                                                    {/* Quick Actions - Appear on Hover */}
                                                    <div className="absolute inset-0 bg-black/0 group-hover:bg-black/5 transition-colors duration-300 flex items-center justify-center opacity-0 group-hover:opacity-100">
                                                        <div className="flex gap-1 bg-white/95 backdrop-blur-sm rounded-xl p-1.5 shadow-lg transform translate-y-2 group-hover:translate-y-0 transition-all duration-300">
                                                            {stockStatus === 'archived' ? (
                                                                <>
                                                                    <button
                                                                        onClick={() => { router.patch(tRoute('inventory.products.restore', { product: product.id }), {}, { onSuccess: () => toast.success('Producto restaurado', { description: `"${product.name}" fue restaurado.` }) }); }}
                                                                        className="p-2 rounded-lg text-green-600 hover:bg-green-100 transition"
                                                                        title="Restaurar"
                                                                    >
                                                                        <RotateCcw className="w-4 h-4" />
                                                                    </button>
                                                                    <button
                                                                        onClick={() => handleDeleteClick(product)}
                                                                        className="p-2 rounded-lg text-red-500 hover:bg-red-100 transition"
                                                                        title="Eliminar"
                                                                    >
                                                                        <Trash2 className="w-4 h-4" />
                                                                    </button>
                                                                </>
                                                            ) : (
                                                                <>
                                                                    <Link
                                                                        href={tRoute('inventory.products.edit', { product: product.id })}
                                                                        className="p-2 rounded-lg text-blue-600 hover:bg-blue-100 transition"
                                                                        title="Editar"
                                                                    >
                                                                        <Pencil className="w-4 h-4" />
                                                                    </Link>
                                                                    <button
                                                                        onClick={() => { router.post(tRoute('inventory.products.duplicate', { product: product.id }), {}, { onSuccess: () => toast.success('Producto duplicado', { description: `Se creó una copia de "${product.name}".` }) }); }}
                                                                        className="p-2 rounded-lg text-purple-500 hover:bg-purple-100 transition"
                                                                        title="Duplicar"
                                                                    >
                                                                        <Copy className="w-4 h-4" />
                                                                    </button>
                                                                    <button
                                                                        onClick={() => handleArchiveClick(product)}
                                                                        className="p-2 rounded-lg text-amber-500 hover:bg-amber-100 transition"
                                                                        title="Archivar"
                                                                    >
                                                                        <Archive className="w-4 h-4" />
                                                                    </button>
                                                                </>
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>

                                                {/* Product Info */}
                                                <div className="p-4">
                                                    {/* Category & Supplier Pills */}
                                                    <div className="flex items-center gap-1.5 mb-2">
                                                        {product.category && (
                                                            <span className="inline-flex items-center px-2 py-0.5 bg-purple-50 text-purple-600 text-[10px] font-medium rounded-md truncate max-w-[80px]">
                                                                {product.category.name}
                                                            </span>
                                                        )}
                                                        {product.supplier && (
                                                            <span className="inline-flex items-center px-2 py-0.5 bg-blue-50 text-blue-600 text-[10px] font-medium rounded-md truncate max-w-[80px]">
                                                                {product.supplier.name}
                                                            </span>
                                                        )}
                                                    </div>

                                                    {/* Name */}
                                                    <h3 className="font-semibold text-gray-900 text-sm leading-tight mb-1 line-clamp-2 min-h-[2.5rem]">
                                                        {product.name}
                                                    </h3>

                                                    {/* SKU */}
                                                    <p className="text-[11px] text-gray-400 font-mono mb-3">
                                                        {product.sku || 'Sin SKU'}
                                                    </p>

                                                    {/* Price & Stock Row */}
                                                    <div className="flex items-end justify-between">
                                                        <div>
                                                            <p className="text-xl font-bold text-primary leading-none">
                                                                {formatPrice(product.price)}
                                                            </p>
                                                            <p className="text-[11px] text-gray-400 mt-0.5">
                                                                Costo: {formatPrice(product.cost)}
                                                            </p>
                                                        </div>
                                                        <div>{getStockBadge(product)}</div>
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                    )}

                                    {/* List View */}
                                    {viewMode === 'list' && (
                                    <div className="space-y-2">
                                        {products.data.map((product) => (
                                            <div
                                                key={product.id}
                                                className="group bg-white rounded-xl border border-gray-100 p-4 hover:shadow-md hover:border-gray-200 transition-all duration-200 flex items-center gap-4"
                                            >
                                                {/* Image with Stock Dot */}
                                                <div className="relative w-14 h-14 bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl overflow-hidden shrink-0">
                                                    {product.image ? (
                                                        <img src={`/storage/${product.image}`} alt={product.name} className="w-full h-full object-cover" />
                                                    ) : (
                                                        <div className="w-full h-full flex items-center justify-center">
                                                            <Package className="w-6 h-6 text-gray-300" />
                                                        </div>
                                                    )}
                                                    <div className="absolute -top-0.5 -left-0.5">
                                                        {getStockDot(product)}
                                                    </div>
                                                </div>

                                                {/* Product Info */}
                                                <div className="flex-1 min-w-0">
                                                    <div className="flex items-center gap-2 mb-0.5">
                                                        <h3 className="font-semibold text-gray-900 truncate">{product.name}</h3>
                                                        {product.stock <= 0 && (
                                                            <span className="shrink-0 bg-red-100 text-red-600 text-[10px] font-bold px-1.5 py-0.5 rounded">
                                                                AGOTADO
                                                            </span>
                                                        )}
                                                    </div>
                                                    <div className="flex items-center gap-2 text-xs text-gray-500">
                                                        <span className="font-mono">{product.sku || 'Sin SKU'}</span>
                                                        {product.category && (
                                                            <>
                                                                <span>•</span>
                                                                <span className="text-purple-600">{product.category.name}</span>
                                                            </>
                                                        )}
                                                        {product.supplier && (
                                                            <>
                                                                <span>•</span>
                                                                <span className="text-blue-600">{product.supplier.name}</span>
                                                            </>
                                                        )}
                                                    </div>
                                                </div>

                                                {/* Price */}
                                                <div className="text-right hidden sm:block">
                                                    <p className="font-bold text-primary text-lg">{formatPrice(product.price)}</p>
                                                    <p className="text-xs text-gray-400">Costo: {formatPrice(product.cost)}</p>
                                                </div>

                                                {/* Stock Badge */}
                                                <div className="hidden md:block w-24 text-center">
                                                    {getStockBadge(product)}
                                                </div>

                                                {/* Actions */}
                                                <div className="flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                                                    <Link
                                                        href={tRoute('inventory.products.edit', { product: product.id })}
                                                        className="p-2 rounded-lg text-blue-600 hover:bg-blue-50 transition"
                                                        title="Editar"
                                                    >
                                                        <Pencil className="w-4 h-4" />
                                                    </Link>
                                                    <button
                                                        onClick={() => { router.post(tRoute('inventory.products.duplicate', { product: product.id }), {}, { onSuccess: () => toast.success('Producto duplicado') }); }}
                                                        className="p-2 rounded-lg text-purple-500 hover:bg-purple-50 transition"
                                                        title="Duplicar"
                                                    >
                                                        <Copy className="w-4 h-4" />
                                                    </button>
                                                    <button
                                                        onClick={() => handleArchiveClick(product)}
                                                        className="p-2 rounded-lg text-amber-500 hover:bg-amber-50 transition"
                                                        title="Archivar"
                                                    >
                                                        <Archive className="w-4 h-4" />
                                                    </button>
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
                                                                <div className="w-10 h-10 bg-gray-100 rounded overflow-hidden shrink-0">
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
                                                                <Link href={tRoute('inventory.products.edit', { product: product.id })} className="p-1.5 rounded text-primary hover:bg-primary/10 transition" title="Editar"><Pencil className="w-4 h-4" /></Link>
                                                                <button onClick={() => handleArchiveClick(product)} className="p-1.5 rounded text-warning hover:bg-warning/10 transition" title="Archivar"><Archive className="w-4 h-4" /></button>
                                                                <button onClick={() => handleDeleteClick(product)} className="p-1.5 rounded text-danger hover:bg-danger/10 transition" title="Eliminar"><Trash2 className="w-4 h-4" /></button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                    )}

                                    {products.data.length === 0 && (
                                        <div className="bg-white rounded-2xl border border-gray-100 p-16 text-center">
                                            <div className="w-20 h-20 mx-auto mb-6 bg-gradient-to-br from-blue-50 to-purple-50 rounded-2xl flex items-center justify-center">
                                                <Package className="w-10 h-10 text-primary/60" />
                                            </div>
                                            <h3 className="text-xl font-semibold text-gray-900 mb-2">
                                                No hay productos {stockStatus ? 'con este filtro' : 'aún'}
                                            </h3>
                                            <p className="text-gray-500 mb-6 max-w-sm mx-auto">
                                                {stockStatus
                                                    ? 'Intenta cambiar los filtros de búsqueda o categoría.'
                                                    : 'Comienza agregando tu primer producto al inventario para empezar a gestionar tu stock.'
                                                }
                                            </p>
                                            {!stockStatus && (
                                                <button
                                                    type="button"
                                                    onClick={() => setProductDrawerOpen(true)}
                                                    className="inline-flex items-center gap-2 px-5 py-2.5 bg-primary text-white text-sm font-medium rounded-xl hover:bg-primary/90 transition-colors shadow-sm"
                                                >
                                                    <Plus className="w-4 h-4" />
                                                    Agregar primer producto
                                                </button>
                                            )}
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

            {/* Product Form Drawer */}
            <ProductFormDrawer
                open={productDrawerOpen}
                onClose={() => setProductDrawerOpen(false)}
                categories={categories}
                suppliers={suppliers}
                onSuccess={() => router.reload()}
            />
        </AuthenticatedLayout>
    );
}
