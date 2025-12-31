import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useTenantRoute } from '@/Hooks/useTenantRoute';
import { useState } from 'react';
import {
    FileText,
    Plus,
    Search,
    Filter,
    Check,
    Clock,
    Eye,
    Trash2,
    Building2,
    Calendar,
    DollarSign
} from 'lucide-react';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

interface Supplier {
    id: number;
    name: string;
}

interface Invoice {
    id: number;
    invoice_number: string;
    invoice_date: string;
    total_amount: number;
    is_paid: boolean;
    status: string;
    supplier: Supplier;
    created_by?: { name: string } | null;
}

interface Props {
    invoices: {
        data: Invoice[];
        links: any[];
        meta?: any;
    };
    suppliers: Supplier[];
    filters: {
        supplier?: string;
        status?: string;
        search?: string;
    };
}

export default function Index({ invoices, suppliers, filters }: Props) {
    const tRoute = useTenantRoute();
    const [search, setSearch] = useState(filters.search || '');
    const [selectedSupplier, setSelectedSupplier] = useState(filters.supplier || 'all');
    const [selectedStatus, setSelectedStatus] = useState(filters.status || 'all');

    const formatPrice = (price: number) => {
        return new Intl.NumberFormat('es-CL', {
            style: 'currency',
            currency: 'CLP',
        }).format(price);
    };

    const formatDate = (date: string) => {
        return new Date(date).toLocaleDateString('es-CL', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        });
    };

    const handleFilter = () => {
        router.get(tRoute('purchases.index'), {
            supplier: selectedSupplier !== 'all' ? selectedSupplier : undefined,
            status: selectedStatus !== 'all' ? selectedStatus : undefined,
            search: search || undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleDelete = (invoice: Invoice) => {
        if (invoice.is_paid) {
            alert('No se puede eliminar una factura pagada.');
            return;
        }
        if (confirm(`¿Estás seguro de eliminar la factura ${invoice.invoice_number}? Esta acción revertirá el stock.`)) {
            router.delete(tRoute('purchases.destroy', { purchase: invoice.id }));
        }
    };

    const handleMarkAsPaid = (invoice: Invoice) => {
        if (confirm(`¿Marcar como pagada la factura ${invoice.invoice_number}?`)) {
            router.patch(tRoute('purchases.pay', { purchase: invoice.id }));
        }
    };

    const getStatusBadge = (invoice: Invoice) => {
        if (invoice.is_paid) {
            return (
                <span className="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium bg-success/10 text-success rounded-full">
                    <Check className="w-3 h-3" />
                    Pagada
                </span>
            );
        }
        return (
            <span className="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium bg-warning/10 text-warning rounded-full">
                <Clock className="w-3 h-3" />
                Pendiente
            </span>
        );
    };

    return (
        <AuthenticatedLayout>
            <Head title="Facturas de Compra" />

            <div className="py-6 px-4 sm:px-6 lg:px-8 max-w-[1600px] mx-auto">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Facturas de Compra</h1>
                        <p className="text-gray-500 mt-1">Gestiona las facturas de compra de tus proveedores</p>
                    </div>
                    <Link
                        href={tRoute('purchases.create')}
                        className="inline-flex items-center gap-2 px-4 py-2.5 bg-primary text-white text-sm font-medium rounded-xl hover:bg-primary/90 transition-colors"
                    >
                        <Plus className="w-4 h-4" />
                        Nueva Factura
                    </Link>
                </div>

                {/* Filters */}
                <div className="bg-white rounded-2xl border border-gray-100 p-4 mb-6">
                    <div className="flex flex-wrap items-center gap-4">
                        {/* Search */}
                        <div className="relative flex-1 min-w-[200px]">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                            <input
                                type="text"
                                placeholder="Buscar por número de factura..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && handleFilter()}
                                className="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                            />
                        </div>

                        {/* Supplier Filter */}
                        <Select value={selectedSupplier} onValueChange={setSelectedSupplier}>
                            <SelectTrigger className="w-[200px] rounded-xl">
                                <SelectValue placeholder="Proveedor" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todos los Proveedores</SelectItem>
                                {suppliers.map(sup => (
                                    <SelectItem key={sup.id} value={sup.id.toString()}>
                                        {sup.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        {/* Status Filter */}
                        <Select value={selectedStatus} onValueChange={setSelectedStatus}>
                            <SelectTrigger className="w-[150px] rounded-xl">
                                <SelectValue placeholder="Estado" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todos</SelectItem>
                                <SelectItem value="paid">Pagadas</SelectItem>
                                <SelectItem value="unpaid">Pendientes</SelectItem>
                            </SelectContent>
                        </Select>

                        <button
                            onClick={handleFilter}
                            className="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-200 transition-colors"
                        >
                            <Filter className="w-4 h-4" />
                            Filtrar
                        </button>
                    </div>
                </div>

                {/* Table */}
                <div className="bg-white rounded-2xl border border-gray-100 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead>
                                <tr className="bg-gray-50 border-b border-gray-100">
                                    <th className="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">
                                        Factura
                                    </th>
                                    <th className="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">
                                        Proveedor
                                    </th>
                                    <th className="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">
                                        Fecha
                                    </th>
                                    <th className="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase">
                                        Total
                                    </th>
                                    <th className="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase">
                                        Estado
                                    </th>
                                    <th className="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50">
                                {invoices.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-12 text-center">
                                            <FileText className="w-12 h-12 mx-auto mb-3 text-gray-300" />
                                            <p className="text-gray-500">No hay facturas de compra</p>
                                            <Link
                                                href={tRoute('purchases.create')}
                                                className="inline-flex items-center gap-2 mt-4 text-primary hover:underline"
                                            >
                                                <Plus className="w-4 h-4" />
                                                Registrar primera factura
                                            </Link>
                                        </td>
                                    </tr>
                                ) : (
                                    invoices.data.map((invoice) => (
                                        <tr key={invoice.id} className="hover:bg-gray-50 transition-colors">
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-3">
                                                    <div className="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center">
                                                        <FileText className="w-5 h-5 text-primary" />
                                                    </div>
                                                    <div>
                                                        <p className="font-medium text-gray-900">{invoice.invoice_number}</p>
                                                        <p className="text-xs text-gray-500">ID: {invoice.id}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-2">
                                                    <Building2 className="w-4 h-4 text-gray-400" />
                                                    <span className="text-sm text-gray-900">{invoice.supplier.name}</span>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-2">
                                                    <Calendar className="w-4 h-4 text-gray-400" />
                                                    <span className="text-sm text-gray-600">{formatDate(invoice.invoice_date)}</span>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex items-center justify-end gap-2">
                                                    <DollarSign className="w-4 h-4 text-gray-400" />
                                                    <span className="font-semibold text-gray-900">{formatPrice(invoice.total_amount)}</span>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                {getStatusBadge(invoice)}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center justify-center gap-2">
                                                    <Link
                                                        href={tRoute('purchases.show', { purchase: invoice.id })}
                                                        className="p-2 text-gray-500 hover:text-primary hover:bg-primary/10 rounded-lg transition-colors"
                                                        title="Ver detalles"
                                                    >
                                                        <Eye className="w-4 h-4" />
                                                    </Link>
                                                    {!invoice.is_paid && (
                                                        <>
                                                            <button
                                                                onClick={() => handleMarkAsPaid(invoice)}
                                                                className="p-2 text-gray-500 hover:text-success hover:bg-success/10 rounded-lg transition-colors"
                                                                title="Marcar como pagada"
                                                            >
                                                                <Check className="w-4 h-4" />
                                                            </button>
                                                            <button
                                                                onClick={() => handleDelete(invoice)}
                                                                className="p-2 text-gray-500 hover:text-danger hover:bg-danger/10 rounded-lg transition-colors"
                                                                title="Eliminar"
                                                            >
                                                                <Trash2 className="w-4 h-4" />
                                                            </button>
                                                        </>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
