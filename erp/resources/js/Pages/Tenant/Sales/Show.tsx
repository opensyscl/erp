import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useTenantRoute } from '@/Hooks/useTenantRoute';
import {
    Receipt,
    ArrowLeft,
    User,
    Calendar,
    CreditCard,
    Banknote,
    Package,
    DollarSign,
    Check,
    AlertTriangle,
    X
} from 'lucide-react';

interface Product {
    id: number;
    name: string;
    sku: string | null;
}

interface SaleItem {
    id: number;
    product: Product;
    quantity: number;
    unit_price: number;
    unit_cost: number | null;
    subtotal: number;
}

interface Sale {
    id: number;
    receipt_number: number;
    subtotal: number;
    tax: number;
    discount: number;
    total: number;
    paid: number;
    change: number;
    payment_method: string;
    status: string;
    notes: string | null;
    cost_of_goods_sold: number;
    created_at: string;
    user?: { name: string } | null;
    items: SaleItem[];
}

interface Props {
    sale: Sale;
}

export default function Show({ sale }: Props) {
    const tRoute = useTenantRoute();

    const formatPrice = (price: number) => {
        return new Intl.NumberFormat('es-CL', {
            style: 'currency',
            currency: 'CLP',
        }).format(price);
    };

    const formatDateTime = (date: string) => {
        return new Date(date).toLocaleString('es-CL', {
            day: '2-digit',
            month: 'long',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const getStatusInfo = (status: string) => {
        switch (status) {
            case 'completed':
                return { label: 'Completada', color: 'success', icon: Check };
            case 'partial_refund':
                return { label: 'Devolución Parcial', color: 'warning', icon: AlertTriangle };
            case 'complete_refund':
                return { label: 'Anulada', color: 'danger', icon: X };
            default:
                return { label: status, color: 'gray', icon: Receipt };
        }
    };

    const getPaymentMethodLabel = (method: string) => {
        switch (method) {
            case 'cash': return 'Efectivo';
            case 'debit': return 'Débito';
            case 'credit': return 'Crédito';
            case 'transfer': return 'Transferencia';
            default: return method;
        }
    };

    const statusInfo = getStatusInfo(sale.status);
    const StatusIcon = statusInfo.icon;
    const profit = sale.total - sale.cost_of_goods_sold;

    return (
        <AuthenticatedLayout>
            <Head title={`Venta #${sale.receipt_number}`} />

            <div className="py-6 px-4 sm:px-6 lg:px-8 max-w-[1200px] mx-auto">
                {/* Header */}
                <div className="flex items-center gap-4 mb-8">
                    <Link
                        href={tRoute('sales.index')}
                        className="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
                    >
                        <ArrowLeft className="w-5 h-5" />
                    </Link>
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Venta #{sale.receipt_number}</h1>
                        <p className="text-gray-500 mt-1">{formatDateTime(sale.created_at)}</p>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Main Content */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Sale Info */}
                        <div className="bg-white rounded-2xl border border-gray-100 p-6">
                            <h2 className="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                <Receipt className="w-5 h-5 text-primary" />
                                Información de la Venta
                            </h2>

                            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div>
                                    <p className="text-xs text-gray-500 uppercase mb-1">Cajero</p>
                                    <p className="font-medium text-gray-900 flex items-center gap-2">
                                        <User className="w-4 h-4 text-gray-400" />
                                        {sale.user?.name || 'N/A'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs text-gray-500 uppercase mb-1">Fecha</p>
                                    <p className="font-medium text-gray-900 flex items-center gap-2">
                                        <Calendar className="w-4 h-4 text-gray-400" />
                                        {new Date(sale.created_at).toLocaleDateString('es-CL')}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs text-gray-500 uppercase mb-1">Método de Pago</p>
                                    <p className="font-medium text-gray-900 flex items-center gap-2">
                                        {sale.payment_method === 'cash' ? (
                                            <Banknote className="w-4 h-4 text-gray-400" />
                                        ) : (
                                            <CreditCard className="w-4 h-4 text-gray-400" />
                                        )}
                                        {getPaymentMethodLabel(sale.payment_method)}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs text-gray-500 uppercase mb-1">Ticket</p>
                                    <p className="font-medium text-gray-900">
                                        #{sale.receipt_number}
                                    </p>
                                </div>
                            </div>

                            {sale.notes && (
                                <div className="mt-4 pt-4 border-t border-gray-100">
                                    <p className="text-xs text-gray-500 uppercase mb-1">Notas</p>
                                    <p className="text-gray-700">{sale.notes}</p>
                                </div>
                            )}
                        </div>

                        {/* Items */}
                        <div className="bg-white rounded-2xl border border-gray-100 overflow-hidden">
                            <div className="p-5 border-b border-gray-100">
                                <h2 className="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                    <Package className="w-5 h-5 text-primary" />
                                    Productos ({sale.items.length})
                                </h2>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="bg-gray-50 border-b border-gray-100">
                                            <th className="text-left px-4 py-3 text-xs font-semibold text-gray-500">Producto</th>
                                            <th className="text-center px-4 py-3 text-xs font-semibold text-gray-500">Cantidad</th>
                                            <th className="text-right px-4 py-3 text-xs font-semibold text-gray-500">Precio Unit.</th>
                                            <th className="text-right px-4 py-3 text-xs font-semibold text-gray-500">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-50">
                                        {sale.items.map((item) => (
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
                                                <td className="px-4 py-3 text-right text-gray-600">
                                                    {formatPrice(item.unit_price)}
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

                            <div className={`flex items-center gap-3 p-4 bg-${statusInfo.color}/10 rounded-xl`}>
                                <div className={`w-10 h-10 rounded-full bg-${statusInfo.color}/20 flex items-center justify-center`}>
                                    <StatusIcon className={`w-5 h-5 text-${statusInfo.color}`} />
                                </div>
                                <div>
                                    <p className={`font-semibold text-${statusInfo.color}`}>{statusInfo.label}</p>
                                </div>
                            </div>
                        </div>

                        {/* Totals */}
                        <div className="bg-white rounded-2xl border border-gray-100 p-6">
                            <h3 className="text-sm font-semibold text-gray-500 uppercase mb-4">Resumen</h3>

                            <div className="space-y-3">
                                <div className="flex justify-between text-sm">
                                    <span className="text-gray-600">Subtotal (Neto)</span>
                                    <span className="text-gray-900">{formatPrice(sale.subtotal)}</span>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span className="text-gray-600">IVA (19%)</span>
                                    <span className="text-gray-900">{formatPrice(sale.tax)}</span>
                                </div>
                                {sale.discount > 0 && (
                                    <div className="flex justify-between text-sm">
                                        <span className="text-gray-600">Descuento</span>
                                        <span className="text-danger">-{formatPrice(sale.discount)}</span>
                                    </div>
                                )}
                                <div className="border-t border-gray-100 pt-3 flex justify-between">
                                    <span className="text-lg font-bold text-gray-900">Total</span>
                                    <span className="text-lg font-bold text-primary">{formatPrice(sale.total)}</span>
                                </div>
                            </div>
                        </div>

                        {/* Payment Info */}
                        <div className="bg-white rounded-2xl border border-gray-100 p-6">
                            <h3 className="text-sm font-semibold text-gray-500 uppercase mb-4">Pago</h3>

                            <div className="space-y-3">
                                <div className="flex justify-between text-sm">
                                    <span className="text-gray-600">Monto Pagado</span>
                                    <span className="text-gray-900">{formatPrice(sale.paid)}</span>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span className="text-gray-600">Vuelto</span>
                                    <span className="text-success">{formatPrice(sale.change)}</span>
                                </div>
                            </div>
                        </div>

                        {/* Profit */}
                        <div className="bg-white rounded-2xl border border-gray-100 p-6">
                            <h3 className="text-sm font-semibold text-gray-500 uppercase mb-4">Rentabilidad</h3>

                            <div className="space-y-3">
                                <div className="flex justify-between text-sm">
                                    <span className="text-gray-600">Costo de Productos</span>
                                    <span className="text-gray-900">{formatPrice(sale.cost_of_goods_sold)}</span>
                                </div>
                                <div className="border-t border-gray-100 pt-3 flex justify-between">
                                    <span className="font-bold text-gray-900">Ganancia</span>
                                    <span className={`font-bold ${profit >= 0 ? 'text-success' : 'text-danger'}`}>
                                        {formatPrice(profit)}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
