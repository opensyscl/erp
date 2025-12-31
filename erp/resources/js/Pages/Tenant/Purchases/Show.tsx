import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useTenantRoute } from '@/Hooks/useTenantRoute';
import {
    FileText,
    ArrowLeft,
    Building2,
    Calendar,
    User,
    Check,
    Clock,
    Package,
    DollarSign
} from 'lucide-react';

interface Supplier {
    id: number;
    name: string;
}

interface Product {
    id: number;
    name: string;
    sku: string | null;
}

interface InvoiceItem {
    id: number;
    product: Product;
    quantity: number;
    previous_cost: number | null;
    new_cost: number;
    margin_percentage: number | null;
    calculated_sale_price: number | null;
    subtotal: number;
}

interface Invoice {
    id: number;
    invoice_number: string;
    invoice_date: string;
    received_date: string | null;
    subtotal: number;
    tax: number;
    total_amount: number;
    is_paid: boolean;
    payment_date: string | null;
    payment_method: string | null;
    status: string;
    notes: string | null;
    supplier: Supplier;
    created_by?: { name: string } | null;
    items: InvoiceItem[];
    created_at: string;
}

interface Props {
    invoice: Invoice;
}

export default function Show({ invoice }: Props) {
    const tRoute = useTenantRoute();

    const formatPrice = (price: number) => {
        return new Intl.NumberFormat('es-CL', {
            style: 'currency',
            currency: 'CLP',
        }).format(price);
    };

    const formatDate = (date: string) => {
        return new Date(date).toLocaleDateString('es-CL', {
            day: '2-digit',
            month: 'long',
            year: 'numeric',
        });
    };

    const handleMarkAsPaid = () => {
        if (confirm('¿Marcar esta factura como pagada?')) {
            router.patch(tRoute('purchases.pay', { purchase: invoice.id }));
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title={`Factura ${invoice.invoice_number}`} />

            <div className="py-6 px-4 sm:px-6 lg:px-8 max-w-[1200px] mx-auto">
                {/* Header */}
                <div className="flex items-center justify-between gap-4 mb-8">
                    <div className="flex items-center gap-4">
                        <Link
                            href={tRoute('purchases.index')}
                            className="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
                        >
                            <ArrowLeft className="w-5 h-5" />
                        </Link>
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900">Factura {invoice.invoice_number}</h1>
                            <p className="text-gray-500 mt-1">Detalles de la factura de compra</p>
                        </div>
                    </div>

                    {!invoice.is_paid && (
                        <button
                            onClick={handleMarkAsPaid}
                            className="inline-flex items-center gap-2 px-4 py-2.5 bg-success text-white text-sm font-medium rounded-xl hover:bg-success/90 transition-colors"
                        >
                            <Check className="w-4 h-4" />
                            Marcar como Pagada
                        </button>
                    )}
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Main Info */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Invoice Details */}
                        <div className="bg-white rounded-2xl border border-gray-100 p-6">
                            <h2 className="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                <FileText className="w-5 h-5 text-primary" />
                                Información de la Factura
                            </h2>

                            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div>
                                    <p className="text-xs text-gray-500 uppercase mb-1">Proveedor</p>
                                    <p className="font-medium text-gray-900 flex items-center gap-2">
                                        <Building2 className="w-4 h-4 text-gray-400" />
                                        {invoice.supplier.name}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs text-gray-500 uppercase mb-1">Fecha Factura</p>
                                    <p className="font-medium text-gray-900 flex items-center gap-2">
                                        <Calendar className="w-4 h-4 text-gray-400" />
                                        {formatDate(invoice.invoice_date)}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs text-gray-500 uppercase mb-1">Fecha Recepción</p>
                                    <p className="font-medium text-gray-900">
                                        {invoice.received_date ? formatDate(invoice.received_date) : '-'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs text-gray-500 uppercase mb-1">Registrada por</p>
                                    <p className="font-medium text-gray-900 flex items-center gap-2">
                                        <User className="w-4 h-4 text-gray-400" />
                                        {invoice.created_by?.name || '-'}
                                    </p>
                                </div>
                            </div>

                            {invoice.notes && (
                                <div className="mt-4 pt-4 border-t border-gray-100">
                                    <p className="text-xs text-gray-500 uppercase mb-1">Notas</p>
                                    <p className="text-gray-700">{invoice.notes}</p>
                                </div>
                            )}
                        </div>

                        {/* Items */}
                        <div className="bg-white rounded-2xl border border-gray-100 overflow-hidden">
                            <div className="p-5 border-b border-gray-100">
                                <h2 className="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                    <Package className="w-5 h-5 text-primary" />
                                    Productos ({invoice.items.length})
                                </h2>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="bg-gray-50 border-b border-gray-100">
                                            <th className="text-left px-4 py-3 text-xs font-semibold text-gray-500">Producto</th>
                                            <th className="text-center px-4 py-3 text-xs font-semibold text-gray-500">Cantidad</th>
                                            <th className="text-right px-4 py-3 text-xs font-semibold text-gray-500">Costo Anterior</th>
                                            <th className="text-right px-4 py-3 text-xs font-semibold text-gray-500">Nuevo Costo</th>
                                            <th className="text-center px-4 py-3 text-xs font-semibold text-gray-500">Margen %</th>
                                            <th className="text-right px-4 py-3 text-xs font-semibold text-gray-500">Precio Venta</th>
                                            <th className="text-right px-4 py-3 text-xs font-semibold text-gray-500">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-50">
                                        {invoice.items.map((item) => (
                                            <tr key={item.id}>
                                                <td className="px-4 py-3">
                                                    <p className="font-medium text-gray-900">{item.product.name}</p>
                                                    {item.product.sku && (
                                                        <p className="text-xs text-gray-500">SKU: {item.product.sku}</p>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-center text-gray-900">
                                                    {item.quantity}
                                                </td>
                                                <td className="px-4 py-3 text-right text-gray-500">
                                                    {item.previous_cost ? formatPrice(item.previous_cost) : '-'}
                                                </td>
                                                <td className="px-4 py-3 text-right text-gray-900 font-medium">
                                                    {formatPrice(item.new_cost)}
                                                </td>
                                                <td className="px-4 py-3 text-center text-gray-600">
                                                    {item.margin_percentage ? `${item.margin_percentage}%` : '-'}
                                                </td>
                                                <td className="px-4 py-3 text-right text-gray-600">
                                                    {item.calculated_sale_price ? formatPrice(item.calculated_sale_price) : '-'}
                                                </td>
                                                <td className="px-4 py-3 text-right text-gray-900 font-medium">
                                                    {formatPrice(item.subtotal)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-6">
                        {/* Status */}
                        <div className="bg-white rounded-2xl border border-gray-100 p-6">
                            <h3 className="text-sm font-semibold text-gray-500 uppercase mb-3">Estado</h3>

                            {invoice.is_paid ? (
                                <div className="flex items-center gap-3 p-4 bg-success/10 rounded-xl">
                                    <div className="w-10 h-10 rounded-full bg-success/20 flex items-center justify-center">
                                        <Check className="w-5 h-5 text-success" />
                                    </div>
                                    <div>
                                        <p className="font-semibold text-success">Pagada</p>
                                        {invoice.payment_date && (
                                            <p className="text-sm text-gray-600">{formatDate(invoice.payment_date)}</p>
                                        )}
                                    </div>
                                </div>
                            ) : (
                                <div className="flex items-center gap-3 p-4 bg-warning/10 rounded-xl">
                                    <div className="w-10 h-10 rounded-full bg-warning/20 flex items-center justify-center">
                                        <Clock className="w-5 h-5 text-warning" />
                                    </div>
                                    <div>
                                        <p className="font-semibold text-warning">Pendiente de Pago</p>
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Totals */}
                        <div className="bg-white rounded-2xl border border-gray-100 p-6">
                            <h3 className="text-sm font-semibold text-gray-500 uppercase mb-4">Resumen</h3>

                            <div className="space-y-3">
                                <div className="flex justify-between text-sm">
                                    <span className="text-gray-600">Subtotal (Neto)</span>
                                    <span className="text-gray-900">{formatPrice(invoice.subtotal)}</span>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span className="text-gray-600">IVA (19%)</span>
                                    <span className="text-gray-900">{formatPrice(invoice.tax)}</span>
                                </div>
                                <div className="border-t border-gray-100 pt-3 flex justify-between">
                                    <span className="text-lg font-bold text-gray-900">Total</span>
                                    <span className="text-lg font-bold text-primary">{formatPrice(invoice.total_amount)}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
