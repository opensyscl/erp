import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, router } from '@inertiajs/react';
import { useTenantRoute } from '@/Hooks/useTenantRoute';
import { useState, useEffect } from 'react';
import { toast } from 'sonner';
import {
    DollarSign,
    Zap,
    Droplets,
    Home,
    Wifi,
    Shield,
    Receipt,
    Wrench,
    Package,
    MoreHorizontal,
    Calendar,
    TrendingDown,
    TrendingUp
} from 'lucide-react';

interface Expense {
    id: number;
    date_paid: string;
    expense_type: string;
    description: string;
    total_amount: number;
    light: number;
    water: number;
    rent: number;
    alarm: number;
    internet: number;
    iva: number;
    repairs: number;
    supplies: number;
    other: number;
}

interface Props {
    kpis: {
        total_month: number;
        total_fixed: number;
        total_variable: number;
        light: number;
        water: number;
        rent: number;
        iva_credit: number;
        iva_credit_previous: number;
    };
    expenses: Expense[];
    selectedMonth: string;
    monthOptions: { value: string; label: string }[];
}

export default function Index({ kpis, expenses, selectedMonth, monthOptions }: Props) {
    const tRoute = useTenantRoute();

    const { data, setData, post, processing, reset } = useForm({
        date_paid: new Date().toISOString().split('T')[0],
        expense_type: 'Fijo',
        description: '',
        light: 0,
        water: 0,
        rent: 0,
        alarm: 0,
        internet: 0,
        iva: 0,
        repairs: 0,
        supplies: 0,
        other: 0,
    });

    const [calculatedTotal, setCalculatedTotal] = useState(0);

    useEffect(() => {
        const total = (data.light || 0) + (data.water || 0) + (data.rent || 0) +
                      (data.alarm || 0) + (data.internet || 0) + (data.iva || 0) +
                      (data.repairs || 0) + (data.supplies || 0) + (data.other || 0);
        setCalculatedTotal(total);
    }, [data]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(tRoute('expenses.store'), {
            onSuccess: () => {
                reset();
                toast.success('Gasto operacional registrado correctamente');
            },
        });
    };

    const handleMonthChange = (month: string) => {
        router.get(tRoute('expenses.index'), { month }, { preserveState: true });
    };

    const formatCurrency = (value: number) => {
        return new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', minimumFractionDigits: 0 }).format(value);
    };

    const KpiCard = ({ title, value, icon: Icon, color = 'text-gray-600' }: { title: string; value: number; icon: any; color?: string }) => (
        <div className="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
            <div className="flex items-center gap-3">
                <div className={`p-2 rounded-lg bg-gray-100 ${color}`}>
                    <Icon className="w-5 h-5" />
                </div>
                <div>
                    <p className="text-xs text-gray-500">{title}</p>
                    <p className={`text-lg font-bold ${color}`}>{formatCurrency(value)}</p>
                </div>
            </div>
        </div>
    );

    return (
        <AuthenticatedLayout>
            <Head title="Gastos Operativos" />

            <div className="py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">💰 Gastos Operativos</h1>
                        <p className="text-sm text-gray-500">Registra y gestiona los gastos de operación</p>
                    </div>
                    <select
                        value={selectedMonth}
                        onChange={(e) => handleMonthChange(e.target.value)}
                        className="px-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary/20"
                    >
                        {monthOptions.map((opt) => (
                            <option key={opt.value} value={opt.value}>{opt.label}</option>
                        ))}
                    </select>
                </div>

                {/* KPI Grid */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <KpiCard title="Gasto Total (Mes)" value={kpis.total_month} icon={TrendingDown} color="text-red-600" />
                    <KpiCard title="Gastos Fijos" value={kpis.total_fixed} icon={Home} color="text-blue-600" />
                    <KpiCard title="Gastos Variables" value={kpis.total_variable} icon={Wrench} color="text-orange-600" />
                    <KpiCard title="IVA Crédito" value={kpis.iva_credit} icon={Receipt} color="text-green-600" />
                    <KpiCard title="Luz" value={kpis.light} icon={Zap} color="text-yellow-600" />
                    <KpiCard title="Agua" value={kpis.water} icon={Droplets} color="text-cyan-600" />
                    <KpiCard title="Arriendo" value={kpis.rent} icon={Home} color="text-purple-600" />
                    <KpiCard title="IVA Mes Anterior" value={kpis.iva_credit_previous} icon={Calendar} color="text-gray-500" />
                </div>

                {/* Form */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-100 mb-6">
                    <div className="p-6 border-b border-gray-100">
                        <h2 className="text-lg font-semibold">Registrar Nuevo Gasto</h2>
                    </div>
                    <form onSubmit={handleSubmit} className="p-6">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Fecha de Pago</label>
                                <input
                                    type="date"
                                    value={data.date_paid}
                                    onChange={(e) => setData('date_paid', e.target.value)}
                                    className="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary/20"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
                                <input
                                    type="text"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder="Ej: Gastos Diciembre 2024"
                                    className="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary/20"
                                />
                            </div>
                        </div>

                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            {/* Gastos Fijos */}
                            <div className="bg-blue-50 rounded-xl p-4">
                                <h4 className="font-semibold text-blue-800 mb-4 flex items-center gap-2">
                                    <Home className="w-4 h-4" /> Gastos Fijos
                                </h4>
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="text-xs text-gray-600">Arriendo</label>
                                        <input type="number" value={data.rent} onChange={(e) => setData('rent', parseFloat(e.target.value) || 0)} className="w-full px-3 py-2 border rounded-lg" />
                                    </div>
                                    <div>
                                        <label className="text-xs text-gray-600">Luz</label>
                                        <input type="number" value={data.light} onChange={(e) => setData('light', parseFloat(e.target.value) || 0)} className="w-full px-3 py-2 border rounded-lg" />
                                    </div>
                                    <div>
                                        <label className="text-xs text-gray-600">Agua</label>
                                        <input type="number" value={data.water} onChange={(e) => setData('water', parseFloat(e.target.value) || 0)} className="w-full px-3 py-2 border rounded-lg" />
                                    </div>
                                    <div>
                                        <label className="text-xs text-gray-600">Alarma</label>
                                        <input type="number" value={data.alarm} onChange={(e) => setData('alarm', parseFloat(e.target.value) || 0)} className="w-full px-3 py-2 border rounded-lg" />
                                    </div>
                                    <div>
                                        <label className="text-xs text-gray-600">Internet</label>
                                        <input type="number" value={data.internet} onChange={(e) => setData('internet', parseFloat(e.target.value) || 0)} className="w-full px-3 py-2 border rounded-lg" />
                                    </div>
                                    <div>
                                        <label className="text-xs text-gray-600">IVA Crédito</label>
                                        <input type="number" value={data.iva} onChange={(e) => setData('iva', parseFloat(e.target.value) || 0)} className="w-full px-3 py-2 border rounded-lg" />
                                    </div>
                                </div>
                            </div>

                            {/* Gastos Variables */}
                            <div className="bg-orange-50 rounded-xl p-4">
                                <h4 className="font-semibold text-orange-800 mb-4 flex items-center gap-2">
                                    <Wrench className="w-4 h-4" /> Gastos Variables
                                </h4>
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="text-xs text-gray-600">Reparaciones</label>
                                        <input type="number" value={data.repairs} onChange={(e) => setData('repairs', parseFloat(e.target.value) || 0)} className="w-full px-3 py-2 border rounded-lg" />
                                    </div>
                                    <div>
                                        <label className="text-xs text-gray-600">Suministros</label>
                                        <input type="number" value={data.supplies} onChange={(e) => setData('supplies', parseFloat(e.target.value) || 0)} className="w-full px-3 py-2 border rounded-lg" />
                                    </div>
                                    <div className="col-span-2">
                                        <label className="text-xs text-gray-600">Otros Gastos</label>
                                        <input type="number" value={data.other} onChange={(e) => setData('other', parseFloat(e.target.value) || 0)} className="w-full px-3 py-2 border rounded-lg" />
                                    </div>
                                </div>

                                <div className="mt-4 flex gap-4">
                                    <label className="flex items-center gap-2">
                                        <input type="radio" name="expense_type" value="Fijo" checked={data.expense_type === 'Fijo'} onChange={(e) => setData('expense_type', e.target.value)} />
                                        <span className="text-sm">Fijo</span>
                                    </label>
                                    <label className="flex items-center gap-2">
                                        <input type="radio" name="expense_type" value="Variable" checked={data.expense_type === 'Variable'} onChange={(e) => setData('expense_type', e.target.value)} />
                                        <span className="text-sm">Variable</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div className="flex items-center justify-between mt-6 pt-6 border-t">
                            <div className="text-lg">
                                <span className="text-gray-600">Total a Registrar:</span>
                                <span className="ml-2 font-bold text-2xl text-primary">{formatCurrency(calculatedTotal)}</span>
                            </div>
                            <button
                                type="submit"
                                disabled={processing || !data.description}
                                className="px-6 py-3 bg-primary text-white rounded-lg font-medium hover:bg-primary/90 disabled:opacity-50"
                            >
                                {processing ? 'Guardando...' : 'Guardar Gasto'}
                            </button>
                        </div>
                    </form>
                </div>

                {/* Table */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <div className="p-6 border-b border-gray-100">
                        <h2 className="text-lg font-semibold">Historial de Gastos</h2>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left font-medium text-gray-600">Fecha</th>
                                    <th className="px-4 py-3 text-left font-medium text-gray-600">Tipo</th>
                                    <th className="px-4 py-3 text-left font-medium text-gray-600">Descripción</th>
                                    <th className="px-4 py-3 text-right font-medium text-gray-600">Total</th>
                                    <th className="px-4 py-3 text-right font-medium text-gray-600">Luz</th>
                                    <th className="px-4 py-3 text-right font-medium text-gray-600">Agua</th>
                                    <th className="px-4 py-3 text-right font-medium text-gray-600">Arriendo</th>
                                    <th className="px-4 py-3 text-right font-medium text-gray-600">IVA</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {expenses.length === 0 ? (
                                    <tr>
                                        <td colSpan={8} className="px-4 py-12 text-center text-gray-500">
                                            No hay gastos registrados para este mes
                                        </td>
                                    </tr>
                                ) : (
                                    expenses.map((expense) => (
                                        <tr key={expense.id} className="hover:bg-gray-50">
                                            <td className="px-4 py-3">{new Date(expense.date_paid).toLocaleDateString('es-CL')}</td>
                                            <td className="px-4 py-3">
                                                <span className={`px-2 py-1 rounded-full text-xs ${expense.expense_type === 'Fijo' ? 'bg-blue-100 text-blue-700' : 'bg-orange-100 text-orange-700'}`}>
                                                    {expense.expense_type}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">{expense.description}</td>
                                            <td className="px-4 py-3 text-right font-semibold">{formatCurrency(expense.total_amount)}</td>
                                            <td className="px-4 py-3 text-right">{formatCurrency(expense.light)}</td>
                                            <td className="px-4 py-3 text-right">{formatCurrency(expense.water)}</td>
                                            <td className="px-4 py-3 text-right">{formatCurrency(expense.rent)}</td>
                                            <td className="px-4 py-3 text-right">{formatCurrency(expense.iva)}</td>
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
