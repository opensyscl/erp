import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useTenantRoute } from '@/Hooks/useTenantRoute';
import { useState } from 'react';
import {
    DollarSign,
    Filter,
    Search,
    X,
    Check,
    RotateCcw,
    Edit2,
    Calendar,
    Wallet,
    AlertCircle
} from 'lucide-react';
import { toast } from 'sonner';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';

interface Props {
    kpis: {
        total_invoices_amount: number;
        paid_amount: number;
        pending_amount: number;
        total_count: number;
        paid_count: number;
        pending_count: number;
    };
    invoices: {
        data: Array<{
            id: number;
            invoice_number: string;
            total_amount: string;
            is_paid: boolean;
            payment_date: string | null;
            invoice_date: string;
            payment_method: string | null;
            supplier: { name: string };
        }>;
        links: any[];
    };
    filters: {
        month: string | null;
        start_date: string | null;
        end_date: string | null;
        status: string;
    };
    monthOptions: Array<{ value: string; label: string }>;
}

const formatCurrency = (amount: number | string) => {
    return new Intl.NumberFormat('es-CL', {
        style: 'currency',
        currency: 'CLP',
        minimumFractionDigits: 0,
    }).format(Number(amount || 0));
};

const formatDate = (dateStr: string) => {
    const [year, month, day] = dateStr.split('-');
    return `${day}-${month}-${year}`;
};

export default function Index({ kpis, invoices, filters, monthOptions }: Props) {
    const tRoute = useTenantRoute();

    // Filters State
    const [selectedMonth, setSelectedMonth] = useState(filters.month || monthOptions[0]?.value);
    const [startDate, setStartDate] = useState(filters.start_date || '');
    const [endDate, setEndDate] = useState(filters.end_date || '');
    const [status, setStatus] = useState(filters.status);

    // Modals State
    const [paymentModal, setPaymentModal] = useState<{ id: number, amount: string } | null>(null);
    const [undoModal, setUndoModal] = useState<{ id: number } | null>(null);
    const [editAmountModal, setEditAmountModal] = useState<{ id: number, amount: string, invoice_number: string, supplier: string } | null>(null);

    // Forms specific state
    const [paymentMethod, setPaymentMethod] = useState('');
    const [newAmount, setNewAmount] = useState('');

    // Handlers
    const handleFilterChange = (key: string, value: any) => {
        const newFilters = { month: selectedMonth, start_date: startDate, end_date: endDate, status: status, [key]: value };

        if (key === 'month') {
             newFilters.start_date = '';
             newFilters.end_date = '';
             setStartDate('');
             setEndDate('');
             setSelectedMonth(value);
        }

        router.get(tRoute('payments.index'), newFilters, { preserveState: true });
    };

    const handleConfirmPayment = () => {
        if (!paymentModal) return;
        if (!paymentMethod) {
            toast.error('Seleccione una forma de pago');
            return;
        }

        router.post(tRoute('payments.pay', { id: paymentModal.id }), {
            payment_method: paymentMethod
        }, {
            onSuccess: () => {
                toast.success('Pago registrado exitosamente');
                setPaymentModal(null);
                setPaymentMethod('');
            },
            onError: () => toast.error('Error al registrar pago')
        });
    };

    const handleConfirmUndo = () => {
        if (!undoModal) return;

        router.post(tRoute('payments.undo', { id: undoModal.id }), {}, {
            onSuccess: () => {
                toast.success('Pago deshecho correctamente');
                setUndoModal(null);
            },
            onError: () => toast.error('Error al deshacer pago')
        });
    };

    const handleConfirmEditAmount = () => {
        if (!editAmountModal) return;
        if (!newAmount || isNaN(Number(newAmount)) || Number(newAmount) < 0) {
            toast.error('Ingrese un monto válido');
            return;
        }

        router.put(tRoute('payments.update-amount', { id: editAmountModal.id }), {
            amount: newAmount
        }, {
            onSuccess: () => {
                toast.success('Monto actualizado correcamente');
                setEditAmountModal(null);
                setNewAmount('');
            },
            onError: () => toast.error('Error al actualizar monto')
        });
    };

    const openEditAmount = (invoice: any) => {
        setEditAmountModal({
            id: invoice.id,
            amount: invoice.total_amount,
            invoice_number: invoice.invoice_number,
            supplier: invoice.supplier.name
        });
        setNewAmount(Number(invoice.total_amount).toFixed(0));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-3">
                    <span className="text-2xl">💸</span>
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Gestión de Pagos
                    </h2>
                </div>
            }
        >
            <Head title="Pagos a Proveedores" />

            <div className="py-8">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                    {/* KPI Grid */}
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <KpiCard
                            title="Monto Total Facturado"
                            value={formatCurrency(kpis.total_invoices_amount)}
                            subVal="Pendiente (KPI HTML Logic?)"
                            icon={<Wallet className="w-5 h-5" />}
                            color="slate"
                        />
                        <KpiCard
                            title="Monto Pagado"
                            value={formatCurrency(kpis.paid_amount)}
                            subVal={`Facturas: ${kpis.paid_count}`}
                            icon={<Check className="w-5 h-5" />}
                            color="emerald"
                        />
                         <KpiCard
                            title="Monto Pendiente"
                            value={formatCurrency(kpis.pending_amount)}
                            subVal={`Facturas: ${kpis.pending_count}`}
                            icon={<AlertCircle className="w-5 h-5" />}
                            color="red"
                        />
                         <KpiCard
                            title="Facturas Pendientes"
                            value={kpis.pending_count.toString()}
                            subVal="Cantidad"
                            icon={<Filter className="w-5 h-5" />}
                            color="blue"
                        />
                    </div>

                    {/* Controls & Table */}
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div className="p-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-4">
                            <h3 className="text-lg font-bold text-gray-800">Facturas de Proveedores</h3>

                            <div className="flex flex-wrap items-center gap-3">
                                {/* Month Filter */}
                                <select
                                    value={selectedMonth || ''}
                                    onChange={(e) => {
                                        setSelectedMonth(e.target.value);
                                        handleFilterChange('month', e.target.value);
                                    }}
                                    className="rounded-lg border-gray-300 text-sm focus:ring-primary focus:border-primary py-1.5"
                                >
                                    <option value="">-- Mes --</option>
                                    {monthOptions.map((opt) => (
                                        <option key={opt.value} value={opt.value}>{opt.label}</option>
                                    ))}
                                </select>

                                {/* Date Range */}
                                <div className="flex items-center gap-2">
                                    <input
                                        type="date"
                                        value={startDate}
                                        onChange={(e) => setStartDate(e.target.value)}
                                        className="rounded-lg border-gray-300 text-sm py-1.5"
                                    />
                                    <span className="text-gray-400">-</span>
                                    <input
                                        type="date"
                                        value={endDate}
                                        onChange={(e) => setEndDate(e.target.value)}
                                        className="rounded-lg border-gray-300 text-sm py-1.5"
                                    />
                                    <button
                                        onClick={() => handleFilterChange('date_range', null)}
                                        className="p-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200"
                                        title="Aplicar Rango"
                                    >
                                        <Filter className="w-4 h-4" />
                                    </button>
                                </div>

                                {/* Status Filter */}
                                <select
                                    value={status}
                                    onChange={(e) => {
                                        setStatus(e.target.value);
                                        handleFilterChange('status', e.target.value);
                                    }}
                                    className="rounded-lg border-gray-300 text-sm focus:ring-primary focus:border-primary py-1.5"
                                >
                                    <option value="all">Todas</option>
                                    <option value="pending">Pendientes</option>
                                    <option value="paid">Pagadas</option>
                                </select>
                            </div>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-gray-50 text-gray-500 font-medium">
                                    <tr>
                                        <th className="px-4 py-3 text-left">N° Factura</th>
                                        <th className="px-4 py-3 text-left">Proveedor</th>
                                        <th className="px-4 py-3 text-left">Fecha</th>
                                        <th className="px-4 py-3 text-right">Monto</th>
                                        <th className="px-4 py-3 text-center">Estado</th>
                                        <th className="px-4 py-3 text-left">Pago</th>
                                        <th className="px-4 py-3 text-left">Método</th>
                                        <th className="px-4 py-3 text-right">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {invoices.data.length === 0 ? (
                                        <tr>
                                            <td colSpan={8} className="px-4 py-8 text-center text-gray-500">
                                                No se encontraron facturas con estos filtros.
                                            </td>
                                        </tr>
                                    ) : (
                                        invoices.data.map((invoice) => (
                                            <tr key={invoice.id} className="hover:bg-gray-50 transition-colors">
                                                <td className="px-4 py-3 font-medium text-gray-900">{invoice.invoice_number}</td>
                                                <td className="px-4 py-3 text-gray-600 truncate max-w-[150px]">{invoice.supplier.name}</td>
                                                <td className="px-4 py-3 text-gray-500">{formatDate(invoice.invoice_date)}</td>
                                                <td className="px-4 py-3 text-right font-medium text-gray-900">{formatCurrency(invoice.total_amount)}</td>
                                                <td className="px-4 py-3 text-center">
                                                    <span className={`px-2 py-1 rounded-full text-xs font-semibold ${invoice.is_paid ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                                                        {invoice.is_paid ? 'PAGADA' : 'PENDIENTE'}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3 text-gray-500 text-xs">
                                                    {invoice.payment_date ? formatDate(invoice.payment_date) : '-'}
                                                </td>
                                                <td className="px-4 py-3 text-gray-500 text-xs">
                                                    {invoice.payment_method || '-'}
                                                </td>
                                                <td className="px-4 py-3 text-right flex justify-end gap-2">
                                                    {!invoice.is_paid ? (
                                                        <button
                                                            onClick={() => setPaymentModal({ id: invoice.id, amount: invoice.total_amount })}
                                                            className="flex items-center gap-1 px-3 py-1.5 bg-green-600 text-white rounded hover:bg-green-700 text-xs"
                                                        >
                                                            <Check className="w-3 h-3" /> Pagar
                                                        </button>
                                                    ) : (
                                                        <button
                                                            onClick={() => setUndoModal({ id: invoice.id })}
                                                            className="flex items-center gap-1 px-3 py-1.5 bg-red-100 text-red-600 rounded hover:bg-red-200 text-xs"
                                                            title="Deshacer Pago"
                                                        >
                                                            <RotateCcw className="w-3 h-3" />
                                                        </button>
                                                    )}

                                                    <button
                                                        onClick={() => openEditAmount(invoice)}
                                                        className="px-2 py-1.5 text-yellow-600 bg-yellow-50 rounded hover:bg-yellow-100"
                                                        title="Editar Monto"
                                                    >
                                                        <Edit2 className="w-4 h-4" />
                                                    </button>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {/* Pay Modal */}
            <Dialog open={!!paymentModal} onOpenChange={(open) => !open && setPaymentModal(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Confirmar Pago</DialogTitle>
                    </DialogHeader>
                    {paymentModal && (
                        <div className="space-y-4">
                            <p className="text-gray-600">
                                Marcar como pagada la factura por: <strong>{formatCurrency(paymentModal.amount)}</strong>
                            </p>
                            <div className="space-y-2">
                                <label className="text-sm font-medium">Forma de Pago:</label>
                                <div className="flex gap-2">
                                    {['Efectivo', 'Transferencia', 'Cheque'].map(method => (
                                        <button
                                            key={method}
                                            onClick={() => setPaymentMethod(method)}
                                            className={`px-4 py-2 rounded-lg text-sm border transition-colors ${
                                                paymentMethod === method
                                                ? 'bg-blue-600 text-white border-blue-600'
                                                : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'
                                            }`}
                                        >
                                            {method}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        </div>
                    )}
                    <DialogFooter>
                         <button onClick={() => setPaymentModal(null)} className="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded">Cancelar</button>
                         <button onClick={handleConfirmPayment} disabled={!paymentMethod} className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50">Confirmar</button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

             {/* Undo Modal */}
             <Dialog open={!!undoModal} onOpenChange={(open) => !open && setUndoModal(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle className="text-red-600">Deshacer Pago</DialogTitle>
                    </DialogHeader>
                    <div className="space-y-4">
                        <p className="text-gray-600">¿Estás seguro de deshacer el pago de esta factura? Volverá a estado pendiente.</p>
                    </div>
                    <DialogFooter>
                         <button onClick={() => setUndoModal(null)} className="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded">Cancelar</button>
                         <button onClick={handleConfirmUndo} className="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">Sí, Deshacer</button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

             {/* Edit Amount Modal */}
             <Dialog open={!!editAmountModal} onOpenChange={(open) => !open && setEditAmountModal(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Editar Monto</DialogTitle>
                    </DialogHeader>
                    {editAmountModal && (
                         <div className="space-y-4">
                             <div className="text-sm text-gray-600 space-y-1">
                                 <p>Factura: <strong>{editAmountModal.invoice_number}</strong></p>
                                 <p>Proveedor: <strong>{editAmountModal.supplier}</strong></p>
                             </div>
                             <div>
                                 <label className="text-sm font-medium">Nuevo Monto (CLP):</label>
                                 <input
                                     type="number"
                                     value={newAmount}
                                     onChange={(e) => setNewAmount(e.target.value)}
                                     className="w-full mt-1 p-2 border rounded-lg focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400"
                                 />
                                 <p className="text-xs text-gray-400 mt-1">Monto actual: {formatCurrency(editAmountModal.amount)}</p>
                             </div>
                         </div>
                    )}
                    <DialogFooter>
                         <button onClick={() => setEditAmountModal(null)} className="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded">Cancelar</button>
                         <button onClick={handleConfirmEditAmount} className="px-4 py-2 bg-yellow-500 text-white rounded hover:bg-yellow-600">Guardar</button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

        </AuthenticatedLayout>
    );
}

const KpiCard = ({ title, value, subVal, icon, color }: any) => {
    const colors: any = {
        emerald: 'border-l-4 border-l-emerald-500 bg-white',
        red: 'border-l-4 border-l-red-500 bg-white',
        blue: 'border-l-4 border-l-blue-500 bg-white',
        slate: 'border-l-4 border-l-slate-500 bg-white',
    };

    return (
        <div className={`p-4 rounded-lg shadow-sm border ${colors[color] || colors.slate}`}>
            <h3 className="text-gray-500 text-sm font-medium mb-1">{title}</h3>
            <p className="text-2xl font-bold text-gray-800">{value}</p>
            <div className="flex items-center gap-2 mt-2 text-xs text-gray-400">
                {icon}
                <span>{subVal}</span>
            </div>
        </div>
    );
};
