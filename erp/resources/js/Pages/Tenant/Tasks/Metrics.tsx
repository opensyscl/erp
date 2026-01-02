import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useTenantRoute } from '@/Hooks/useTenantRoute';
import {
    ArrowLeft,
    CheckCircle2,
    ListTodo,
    Clock,
    AlertCircle
} from 'lucide-react';
import { Bar, Doughnut } from 'react-chartjs-2';
import 'chart.js/auto';

interface Props {
    metrics: {
        total_tasks: number;
        completed_tasks: number;
        completion_rate: number;
        avg_cycle_time: number;
        status_counts: Record<string, number>;
        priority_counts: Record<string, number>;
        throughput: Array<{ date: string; count: number }>;
    };
}

export default function Metrics({ metrics }: Props) {
    const tRoute = useTenantRoute();

    // Chart Configs
    const throughputData = {
        labels: metrics.throughput.map(d => d.date),
        datasets: [
            {
                label: 'Tareas Completadas',
                data: metrics.throughput.map(d => d.count),
                backgroundColor: 'rgba(16, 185, 129, 0.6)',
                borderColor: 'rgba(16, 185, 129, 1)',
                borderWidth: 1,
                borderRadius: 4,
            },
        ],
    };

    const priorityData = {
        labels: ['Alta', 'Media', 'Baja'],
        datasets: [
            {
                data: [
                    metrics.priority_counts['alta'] || 0,
                    metrics.priority_counts['media'] || 0,
                    metrics.priority_counts['baja'] || 0,
                ],
                backgroundColor: [
                    'rgba(239, 68, 68, 0.8)', // Red
                    'rgba(245, 158, 11, 0.8)', // Amber
                    'rgba(16, 185, 129, 0.8)', // Emerald
                ],
                borderWidth: 0,
            },
        ],
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-4">
                    <button
                        onClick={() => router.get(tRoute('tasks.index'))}
                        className="p-2 rounded-full hover:bg-gray-100 transition-colors"
                        title="Volver al Kanban"
                    >
                        <ArrowLeft className="w-5 h-5 text-gray-600" />
                    </button>
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Métricas de Tareas
                    </h2>
                </div>
            }
        >
            <Head title="Métricas de Tareas" />

            <div className="py-8">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                    {/* KPI Cards */}
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <KpiCard
                            title="Total Tareas"
                            value={metrics.total_tasks}
                            icon={<ListTodo className="w-5 h-5" />}
                            color="blue"
                        />
                        <KpiCard
                            title="Completadas"
                            value={metrics.completed_tasks}
                            icon={<CheckCircle2 className="w-5 h-5" />}
                            color="emerald"
                        />
                        <KpiCard
                            title="Tasa Finalización"
                            value={`${metrics.completion_rate}%`}
                            icon={<BarChart3 className="w-5 h-5" />}
                            color="purple"
                        />
                        <KpiCard
                            title="Ciclo Promedio"
                            value={`${metrics.avg_cycle_time} d`}
                            sub="Días para completar"
                            icon={<Clock className="w-5 h-5" />}
                            color="amber"
                        />
                    </div>

                    {/* Charts Grid */}
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        {/* Throughput */}
                        <div className="lg:col-span-2 bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                             <h3 className="font-semibold text-gray-800 mb-4">Throughput (Últimos 30 días)</h3>
                             <div className="h-64">
                                 <Bar data={throughputData} options={{ maintainAspectRatio: false }} />
                             </div>
                        </div>

                        {/* Priorities */}
                        <div className="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                             <h3 className="font-semibold text-gray-800 mb-4">Por Prioridad</h3>
                             <div className="h-48 flex justify-center">
                                 <Doughnut data={priorityData} options={{ maintainAspectRatio: false }} />
                             </div>
                             <div className="mt-6 space-y-2">
                                 <LegendItem label="Alta" value={metrics.priority_counts['alta'] || 0} color="bg-red-500" />
                                 <LegendItem label="Media" value={metrics.priority_counts['media'] || 0} color="bg-amber-500" />
                                 <LegendItem label="Baja" value={metrics.priority_counts['baja'] || 0} color="bg-emerald-500" />
                             </div>
                        </div>
                    </div>

                    {/* Status Breakdown */}
                    <div className="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                        <h3 className="font-semibold text-gray-800 mb-4">Desglose por Estado</h3>
                        <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
                             <StatusCard label="Pendiente" value={metrics.status_counts['nuevo'] || 0} color="bg-blue-50 text-blue-700" />
                             <StatusCard label="Iniciado" value={metrics.status_counts['iniciado'] || 0} color="bg-emerald-50 text-emerald-700" />
                             <StatusCard label="En Proceso" value={metrics.status_counts['en_progreso'] || 0} color="bg-amber-50 text-amber-700" />
                             <StatusCard label="Completado" value={metrics.status_counts['completado'] || 0} color="bg-indigo-50 text-indigo-700" />
                             <StatusCard label="Cancelado" value={metrics.status_counts['cancelado'] || 0} color="bg-gray-50 text-gray-600" />
                        </div>
                    </div>

                </div>
            </div>
        </AuthenticatedLayout>
    );
}

const KpiCard = ({ title, value, sub, icon, color }: any) => {
    const colors: any = {
        emerald: 'border-l-4 border-l-emerald-500 bg-white',
        blue: 'border-l-4 border-l-blue-500 bg-white',
        purple: 'border-l-4 border-l-purple-500 bg-white',
        amber: 'border-l-4 border-l-amber-500 bg-white',
    };

    return (
        <div className={`p-4 rounded-lg shadow-sm border ${colors[color]}`}>
            <h3 className="text-gray-500 text-sm font-medium mb-1 flex items-center gap-2">
                {icon} {title}
            </h3>
            <p className="text-2xl font-bold text-gray-800">{value}</p>
            {sub && <p className="text-xs text-gray-400 mt-1">{sub}</p>}
        </div>
    );
};

const LegendItem = ({ label, value, color }: any) => (
    <div className="flex justify-between items-center text-sm">
        <div className="flex items-center gap-2">
            <span className={`w-3 h-3 rounded-full ${color}`}></span>
            <span className="text-gray-600">{label}</span>
        </div>
        <span className="font-medium text-gray-800">{value}</span>
    </div>
);

const StatusCard = ({ label, value, color }: any) => (
    <div className={`p-3 rounded-lg text-center ${color}`}>
        <div className="text-2xl font-bold">{value}</div>
        <div className="text-xs font-medium uppercase tracking-wide opacity-80">{label}</div>
    </div>
);

import { BarChart3 } from 'lucide-react';
