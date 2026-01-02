import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { useTenantRoute } from '@/Hooks/useTenantRoute';
import { useState, useEffect } from 'react';
import {
    DollarSign,
    Calculator,
    RefreshCw,
    Filter,
    TrendingUp,
    TrendingDown,
    Landmark,
    Wallet,
    ArrowUpRight,
    ArrowDownRight,
    Search,
    X
} from 'lucide-react';
import { toast } from 'sonner';

interface Props {
    kpis: {
        total_cash: number;
        total_pos1: number;
        total_pos2: number;
        total_meli: number;
        total_bchile: number;
        total_bsantander: number;
        total_other: number;
    };
    closings: {
        data: Array<{
            id: number;
            closing_date: string;
            starting_cash: string;
            ending_cash: string;
            pos1_sales: string;
            pos2_sales: string;
            deposit_meli: string;
            deposit_bchile: string;
            deposit_bsantander: string;
            other_outgoings: string;
            total_outgoings: string;
            total_day_income: string;
            income_plus_outgoings: string;
        }>;
        links: any[];
    };
    filters: {
        month: string | null;
        start_date: string | null;
        end_date: string | null;
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

export default function Index({ kpis, closings, filters, monthOptions }: Props) {
    const tRoute = useTenantRoute();

    // Filters State
    const [selectedMonth, setSelectedMonth] = useState(filters.month || monthOptions[0]?.value);
    const [startDate, setStartDate] = useState(filters.start_date || '');
    const [endDate, setEndDate] = useState(filters.end_date || '');

    // Form State
    const { data, setData, post, processing, errors, reset } = useForm({
        starting_cash: '',
        ending_cash: '',
        pos1_sales: '',
        pos2_sales: '',
        deposit_meli: '',
        deposit_bchile: '',
        deposit_bsantander: '',
        other_outgoings: '',
    });

    // Derived Calculations for Live Preview
    const startingCash = Number(data.starting_cash || 0);
    const endingCash = Number(data.ending_cash || 0);
    const pos1Sales = Number(data.pos1_sales || 0);
    const pos2Sales = Number(data.pos2_sales || 0);
    const depositMeli = Number(data.deposit_meli || 0);
    const depositBchile = Number(data.deposit_bchile || 0);
    const depositBsantander = Number(data.deposit_bsantander || 0);
    const otherOutgoings = Number(data.other_outgoings || 0);

    const totalOutgoings = depositMeli + depositBchile + depositBsantander + otherOutgoings;
    const totalDayIncome = (endingCash - startingCash) + pos1Sales + pos2Sales;
    const incomePlusOutgoings = totalDayIncome + totalOutgoings;

    // Handlers
    const handleMonthChange = (month: string) => {
        setSelectedMonth(month);
        setStartDate('');
        setEndDate('');
        router.get(tRoute('cash-closings.index'), { month }, { preserveState: true });
    };

    const handleDateRangeFilter = () => {
        if (startDate && endDate) {
            router.get(tRoute('cash-closings.index'), { start_date: startDate, end_date: endDate }, { preserveState: true });
        }
    };

    const handleResetFilters = () => {
        router.get(tRoute('cash-closings.index'), {}, { preserveState: false });
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(tRoute('cash-closings.store'), {
            onSuccess: () => {
                toast.success('Cierre de caja guardado exitosamente');
                reset();
            },
            onError: () => {
                toast.error('Error al guardar el cierre. Revisa los datos.');
            }
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-3">
                    <span className="text-2xl">💵</span>
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Cuadres de Caja
                    </h2>
                </div>
            }
        >
            <Head title="Cuadre de Caja" />

            <div className="py-8">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                    {/* KPI Cards (Month Context) */}
                    <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3 text-sm">
                        <KpiCard title="Efectivo en Caja (Mes)" value={kpis.total_cash} icon={<Wallet className="w-4 h-4" />} color="emerald" />
                        <KpiCard title="Ventas POS 1" value={kpis.total_pos1} icon={<DollarSign className="w-4 h-4" />} color="blue" />
                        <KpiCard title="Ventas POS 2" value={kpis.total_pos2} icon={<DollarSign className="w-4 h-4" />} color="indigo" />
                        <KpiCard title="Dep. Santander" value={kpis.total_bsantander} icon={<Landmark className="w-4 h-4" />} color="red" />
                        <KpiCard title="Dep. Banco Chile" value={kpis.total_bchile} icon={<Landmark className="w-4 h-4" />} color="blue" />
                        <KpiCard title="Dep. MELI" value={kpis.total_meli} icon={<Landmark className="w-4 h-4" />} color="yellow" />
                        <KpiCard title="Otras Salidas" value={kpis.total_other} icon={<ArrowUpRight className="w-4 h-4" />} color="slate" />
                    </div>

                    {/* Form Card */}
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200">
                        <div className="p-4 border-b border-gray-100 flex justify-between items-center">
                            <h3 className="text-lg font-bold text-gray-800 flex items-center gap-2">
                                <Calculator className="w-5 h-5 text-gray-400" />
                                Registrar Cierre Diario
                            </h3>
                        </div>

                        <form onSubmit={handleSubmit} className="p-6">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                                {/* Column 1: Ingresos */}
                                <div className="space-y-4">
                                    <h4 className="font-semibold text-gray-700 border-b pb-2 mb-4">Cierre del Día (Ingresos)</h4>

                                    <InputGroup label="Efectivo Inicial" id="starting_cash" value={data.starting_cash} onChange={e => setData('starting_cash', e.target.value)} error={errors.starting_cash} />
                                    <InputGroup label="Ventas POS 1" id="pos1_sales" value={data.pos1_sales} onChange={e => setData('pos1_sales', e.target.value)} error={errors.pos1_sales} />
                                    <InputGroup label="Ventas POS 2" id="pos2_sales" value={data.pos2_sales} onChange={e => setData('pos2_sales', e.target.value)} error={errors.pos2_sales} />
                                    <InputGroup label="Efectivo Final (en caja)" id="ending_cash" value={data.ending_cash} onChange={e => setData('ending_cash', e.target.value)} error={errors.ending_cash} />
                                </div>

                                {/* Column 2: Egresos & Results */}
                                <div className="space-y-4">
                                    <h4 className="font-semibold text-gray-700 border-b pb-2 mb-4">Egresos</h4>

                                    <InputGroup label="Depósito a MELI" id="deposit_meli" value={data.deposit_meli} onChange={e => setData('deposit_meli', e.target.value)} error={errors.deposit_meli} />
                                    <InputGroup label="Depósito a Banco de Chile" id="deposit_bchile" value={data.deposit_bchile} onChange={e => setData('deposit_bchile', e.target.value)} error={errors.deposit_bchile} />
                                    <InputGroup label="Depósito a Banco Santander" id="deposit_bsantander" value={data.deposit_bsantander} onChange={e => setData('deposit_bsantander', e.target.value)} error={errors.deposit_bsantander} />
                                    <InputGroup label="Otras Salidas" id="other_outgoings" value={data.other_outgoings} onChange={e => setData('other_outgoings', e.target.value)} error={errors.other_outgoings} />

                                    {/* Live Calculations */}
                                    <div className="mt-8 pt-4 border-t grid grid-cols-1 gap-3">
                                        <div className="flex justify-between items-center text-sm">
                                            <span className="text-gray-600">Total Ingresos (Flujo Neto)</span>
                                            <span className="font-bold text-gray-800">{formatCurrency(totalDayIncome)}</span>
                                        </div>
                                        <div className="flex justify-between items-center text-sm">
                                            <span className="text-gray-600">Total Egresos</span>
                                            <span className="font-bold text-gray-800">{formatCurrency(totalOutgoings)}</span>
                                        </div>
                                        <div className={`flex justify-between items-center p-3 rounded-lg border ${incomePlusOutgoings >= 0 ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'}`}>
                                            <span className={`font-bold ${incomePlusOutgoings >= 0 ? 'text-green-800' : 'text-red-800'}`}>Ingresos + Egresos</span>
                                            <span className={`text-xl font-bold ${incomePlusOutgoings >= 0 ? 'text-green-700' : 'text-red-700'}`}>
                                                {formatCurrency(incomePlusOutgoings)}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div className="mt-6 flex justify-end">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="px-6 py-2.5 bg-gray-900 text-white font-medium rounded-lg hover:bg-gray-800 focus:ring-4 focus:ring-gray-200 transition-colors disabled:opacity-50"
                                >
                                    {processing ? 'Guardando...' : 'Guardar Cierre de Caja'}
                                </button>
                            </div>
                        </form>
                    </div>

                    {/* History Table */}
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div className="p-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-4">
                            <h3 className="text-lg font-bold text-gray-800">Últimos Cierres Registrados</h3>

                            {/* Filters */}
                            <div className="flex flex-wrap items-center gap-4">
                                <div className="flex items-center gap-2">
                                    <select
                                        value={selectedMonth || ''}
                                        onChange={(e) => handleMonthChange(e.target.value)}
                                        className="rounded-lg border-gray-300 text-sm focus:ring-primary focus:border-primary"
                                    >
                                        <option value="">-- Mes --</option>
                                        {monthOptions.map((opt) => (
                                            <option key={opt.value} value={opt.value}>{opt.label}</option>
                                        ))}
                                    </select>
                                </div>

                                <div className="h-6 w-px bg-gray-200 hidden sm:block" />

                                <div className="flex items-center gap-2">
                                    <input
                                        type="date"
                                        value={startDate}
                                        onChange={(e) => setStartDate(e.target.value)}
                                        className="rounded-lg border-gray-300 text-sm w-36"
                                    />
                                    <span className="text-gray-400">-</span>
                                    <input
                                        type="date"
                                        value={endDate}
                                        onChange={(e) => setEndDate(e.target.value)}
                                        className="rounded-lg border-gray-300 text-sm w-36"
                                    />
                                    <button
                                        onClick={handleDateRangeFilter}
                                        className="p-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200"
                                        title="Filtrar por rango"
                                    >
                                        <Filter className="w-4 h-4" />
                                    </button>
                                    {(startDate || endDate) && (
                                        <button
                                            onClick={handleResetFilters}
                                            className="p-2 text-red-500 hover:bg-red-50 rounded-lg"
                                            title="Limpiar filtros"
                                        >
                                            <X className="w-4 h-4" />
                                        </button>
                                    )}
                                </div>
                            </div>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-gray-50 text-gray-500 font-medium">
                                    <tr>
                                        <th className="px-4 py-3 text-left">Fecha</th>
                                        <th className="px-4 py-3 text-right">Efectivo Inicial</th>
                                        <th className="px-4 py-3 text-right">Ventas POS 1</th>
                                        <th className="px-4 py-3 text-right">Ventas POS 2</th>
                                        <th className="px-4 py-3 text-right">Efectivo Final</th>
                                        <th className="px-4 py-3 text-right hidden lg:table-cell">Dep. MELI</th>
                                        <th className="px-4 py-3 text-right hidden lg:table-cell">Dep. Chile</th>
                                        <th className="px-4 py-3 text-right hidden lg:table-cell">Dep. Santander</th>
                                        <th className="px-4 py-3 text-right hidden lg:table-cell">Otras Sal.</th>
                                        <th className="px-4 py-3 text-right font-semibold">Total Egresos</th>
                                        <th className="px-4 py-3 text-right font-semibold">Ingresos + Egresos</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {closings.data.length === 0 ? (
                                        <tr>
                                            <td colSpan={11} className="px-4 py-8 text-center text-gray-500">
                                                No hay registros para este período
                                            </td>
                                        </tr>
                                    ) : (
                                        closings.data.map((row) => (
                                            <tr key={row.id} className="hover:bg-gray-50 transition-colors">
                                                <td className="px-4 py-3 font-medium text-gray-900 whitespace-nowrap">{formatDate(row.closing_date)}</td>
                                                <td className="px-4 py-3 text-right text-gray-600">{formatCurrency(row.starting_cash)}</td>
                                                <td className="px-4 py-3 text-right text-gray-600">{formatCurrency(row.pos1_sales)}</td>
                                                <td className="px-4 py-3 text-right text-gray-600">{formatCurrency(row.pos2_sales)}</td>
                                                <td className="px-4 py-3 text-right text-gray-600">{formatCurrency(row.ending_cash)}</td>
                                                <td className="px-4 py-3 text-right text-gray-400 hidden lg:table-cell">{formatCurrency(row.deposit_meli)}</td>
                                                <td className="px-4 py-3 text-right text-gray-400 hidden lg:table-cell">{formatCurrency(row.deposit_bchile)}</td>
                                                <td className="px-4 py-3 text-right text-gray-400 hidden lg:table-cell">{formatCurrency(row.deposit_bsantander)}</td>
                                                <td className="px-4 py-3 text-right text-gray-400 hidden lg:table-cell">{formatCurrency(row.other_outgoings)}</td>
                                                <td className="px-4 py-3 text-right font-medium text-orange-600">{formatCurrency(row.total_outgoings)}</td>
                                                <td className={`px-4 py-3 text-right font-bold ${Number(row.income_plus_outgoings) < 0 ? 'text-red-600' : 'text-green-600'}`}>
                                                    {formatCurrency(row.income_plus_outgoings)}
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
        </AuthenticatedLayout>
    );
}

// Subcomponents
const InputGroup = ({ label, id, value, onChange, error }: { label: string, id: string, value: any, onChange: (e: any) => void, error?: string }) => (
    <div>
        <label htmlFor={id} className="block text-sm font-medium text-gray-700 mb-1">{label}</label>
        <div className="relative">
            <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <span className="text-gray-500 sm:text-sm">$</span>
            </div>
            <input
                type="number"
                id={id}
                className={`pl-7 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 sm:text-sm ${error ? 'border-red-300' : ''}`}
                placeholder="0"
                value={value}
                onChange={onChange}
            />
        </div>
        {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
    </div>
);

const KpiCard = ({ title, value, icon, color }: { title: string, value: number, icon: any, color: string }) => {
    const colorClasses: Record<string, string> = {
        emerald: 'bg-emerald-50 text-emerald-700 border-emerald-100',
        blue: 'bg-blue-50 text-blue-700 border-blue-100',
        indigo: 'bg-indigo-50 text-indigo-700 border-indigo-100',
        red: 'bg-red-50 text-red-700 border-red-100',
        yellow: 'bg-yellow-50 text-yellow-700 border-yellow-100',
        slate: 'bg-slate-50 text-slate-700 border-slate-100',
    };

    return (
        <div className={`p-3 rounded-lg border ${colorClasses[color] || colorClasses.slate}`}>
            <div className="flex items-center gap-2 mb-1 opacity-80">
                {icon}
                <span className="text-xs font-semibold truncate">{title}</span>
            </div>
            <p className="text-lg font-bold">{formatCurrency(value)}</p>
        </div>
    );
};
