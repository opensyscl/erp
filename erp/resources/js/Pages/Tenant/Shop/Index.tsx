import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, Link } from '@inertiajs/react';
import { useTenantRoute } from '@/Hooks/useTenantRoute';
import { useState, useEffect } from 'react';
import {
    Search,
    Filter,
    ShoppingBag,
    Tag,
    X,
    ChevronDown,
    LayoutGrid,
    List
} from 'lucide-react';

interface Product {
    id: number;
    name: string;
    description: string | null;
    price: number;
    stock: number;
    image_url: string | null;
    category?: { id: number; name: string; };
    barcode: string | null;
    sku: string | null;
}

interface Category {
    id: number;
    name: string;
}

interface Props {
    products: {
        data: Product[];
        links: any[];
        current_page: number;
        last_page: number;
        total: number;
    };
    categories: Category[];
    filters: {
        search?: string;
        category_id?: string;
        sort?: string;
    };
}

export default function Index({ products, categories, filters }: Props) {
    const tRoute = useTenantRoute();
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [selectedCategory, setSelectedCategory] = useState(filters.category_id || '');
    const [sortOrder, setSortOrder] = useState(filters.sort || 'newest');
    const [isFilterOpen, setIsFilterOpen] = useState(false);

    // Debounce search
    useEffect(() => {
        const timer = setTimeout(() => {
            if (searchTerm !== (filters.search || '')) {
                updateFilters('search', searchTerm);
            }
        }, 500);
        return () => clearTimeout(timer);
    }, [searchTerm]);

    const updateFilters = (key: string, value: string) => {
        const newFilters: any = {
            search: key === 'search' ? value : searchTerm,
            category_id: key === 'category_id' ? value : selectedCategory,
            sort: key === 'sort' ? value : sortOrder,
        };

        // Clean empty filters
        Object.keys(newFilters).forEach(k => {
             if (!newFilters[k]) delete newFilters[k];
        });

        router.get(tRoute('shop.index'), newFilters, {
            preserveState: true,
            preserveScroll: true,
            replace: true
        });
    };

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(amount);
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 flex items-center gap-2">
                    <ShoppingBag className="w-5 h-5 text-pink-500" />
                    Catálogo de Productos
                </h2>
            }
        >
            <Head title="Catálogo" />

            <div className="py-8 bg-gray-50 min-h-screen">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                    {/* Header Controls */}
                    <div className="bg-white p-4 rounded-xl shadow-sm border border-gray-100 mb-6 sticky top-4 z-20">
                        <div className="flex flex-col md:flex-row gap-4 items-center justify-between">

                            {/* Search */}
                            <div className="relative w-full md:w-96">
                                <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 w-4 h-4" />
                                <input
                                    type="text"
                                    placeholder="Buscar productos..."
                                    value={searchTerm}
                                    onChange={(e) => setSearchTerm(e.target.value)}
                                    className="w-full pl-10 pr-4 py-2 bg-gray-50 border-gray-200 rounded-lg focus:ring-2 focus:ring-pink-500/20 focus:border-pink-500 transition-all text-sm"
                                />
                                {searchTerm && (
                                    <button
                                        onClick={() => { setSearchTerm(''); updateFilters('search', ''); }}
                                        className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    >
                                        <X className="w-3 h-3" />
                                    </button>
                                )}
                            </div>

                            {/* Filters & Sort */}
                            <div className="flex gap-2 w-full md:w-auto overflow-x-auto pb-1 md:pb-0">

                                <select
                                    value={selectedCategory}
                                    onChange={(e) => { setSelectedCategory(e.target.value); updateFilters('category_id', e.target.value); }}
                                    className="pl-3 pr-8 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-600 focus:ring-2 focus:ring-pink-500/20 focus:border-pink-500 cursor-pointer min-w-[150px]"
                                >
                                    <option value="">Todas las Categorías</option>
                                    {categories.map(cat => (
                                        <option key={cat.id} value={cat.id}>{cat.name}</option>
                                    ))}
                                </select>

                                <select
                                    value={sortOrder}
                                    onChange={(e) => { setSortOrder(e.target.value); updateFilters('sort', e.target.value); }}
                                    className="pl-3 pr-8 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-600 focus:ring-2 focus:ring-pink-500/20 focus:border-pink-500 cursor-pointer"
                                >
                                    <option value="newest">Más Nuevos</option>
                                    <option value="oldest">Más Antiguos</option>
                                    <option value="price_asc">Menor Precio</option>
                                    <option value="price_desc">Mayor Precio</option>
                                    <option value="name_asc">Nombre (A-Z)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    {/* Results Count */}
                    <div className="flex justify-between items-center mb-4 text-sm text-gray-500 px-1">
                        <span>Mostrando {products.data.length} de {products.total} productos</span>
                        {/* Pagination Links (Desktops) */}
                        <div className="hidden md:flex gap-1">
                            {products.links.map((link, i) => (
                                link.url ? (
                                    <Link
                                        key={i}
                                        href={link.url}
                                        only={['products']}
                                        className={`px-3 py-1 rounded-md border ${link.active ? 'bg-pink-50 border-pink-200 text-pink-600 font-medium' : 'bg-white border-gray-200 hover:bg-gray-50'}`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ) : (
                                    <span key={i} className="px-3 py-1 text-gray-300" dangerouslySetInnerHTML={{ __html: link.label }} />
                                )
                            ))}
                        </div>
                    </div>

                    {/* Product Grid */}
                    {products.data.length > 0 ? (
                        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4">
                            {products.data.map((product) => (
                                <div key={product.id} className="group bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-all duration-300 flex flex-col h-full hover:border-pink-100">
                                    {/* Image */}
                                    <div className="aspect-square bg-gray-50 relative overflow-hidden">
                                        {product.image_url ? (
                                            <img
                                                src={product.image_url}
                                                alt={product.name}
                                                className="w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-500"
                                                loading="lazy"
                                            />
                                        ) : (
                                            <div className="w-full h-full flex items-center justify-center text-gray-300">
                                                <ShoppingBag className="w-12 h-12 opacity-20" />
                                            </div>
                                        )}
                                        {product.stock <= 5 && product.stock > 0 && (
                                            <span className="absolute top-2 right-2 bg-orange-100 text-orange-700 text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm">
                                                ¡Quedan {product.stock}!
                                            </span>
                                        )}
                                    </div>

                                    {/* Content */}
                                    <div className="p-3 flex flex-col flex-grow">
                                        <div className="text-xs text-gray-400 mb-1 flex items-center gap-1">
                                            <Tag className="w-3 h-3" />
                                            <span className="truncate">{product.category?.name || 'Sin Categoría'}</span>
                                        </div>

                                        <h3 className="font-medium text-gray-800 text-sm leading-tight mb-2 line-clamp-2 min-h-[2.5em]" title={product.name}>
                                            {product.name}
                                        </h3>

                                        <div className="mt-auto pt-2 border-t border-gray-50 flex items-end justify-between">
                                            <div>
                                                <span className="block text-lg font-bold text-gray-900 leading-none">
                                                    {formatCurrency(Number(product.price))}
                                                </span>
                                                <span className="text-[10px] text-gray-400">
                                                    COD: {product.sku || product.barcode || 'N/A'}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="text-center py-20 bg-white rounded-xl border border-dashed border-gray-200">
                            <ShoppingBag className="w-16 h-16 text-gray-200 mx-auto mb-4" />
                            <h3 className="text-lg font-medium text-gray-900">No se encontraron productos</h3>
                            <p className="text-gray-500">Intenta con otra búsqueda o categoría</p>
                            <button
                                onClick={() => { setSearchTerm(''); setSelectedCategory(''); updateFilters('search', ''); updateFilters('category_id', ''); }}
                                className="mt-4 px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm font-medium transition-colors"
                            >
                                Limpiar Filtros
                            </button>
                        </div>
                    )}

                    {/* Mobile Pagination */}
                    <div className="mt-6 flex justify-center md:hidden pb-8">
                         <div className="flex gap-1 overflow-x-auto py-2">
                            {products.links.map((link, i) => (
                                link.url ? (
                                    <Link
                                        key={i}
                                        href={link.url}
                                        only={['products']}
                                        className={`px-3 py-1 rounded-md border min-w-[40px] text-center ${link.active ? 'bg-pink-50 border-pink-200 text-pink-600 font-medium' : 'bg-white border-gray-200'}`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ) : null
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
