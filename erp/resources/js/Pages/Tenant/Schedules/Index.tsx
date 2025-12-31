import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useTenantRoute } from '@/Hooks/useTenantRoute';
import { useState, useMemo } from 'react';
import { toast } from 'sonner';
import {
    Calendar,
    Users,
    Clock,
    Plus,
    X,
    Edit2,
    Trash2,
    ChevronDown,
    Sun,
    Palmtree
} from 'lucide-react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';

// Axios is globally configured by Laravel with CSRF token
declare global {
    interface Window {
        axios: any;
    }
}

interface Employee {
    id: number;
    name: string;
    is_active: boolean;
}

interface Shift {
    id: number;
    name: string;
    start_time: string;
    end_time: string;
    color_code: string;
}

interface ScheduleEntry {
    id: number;
    is_day_off: boolean;
    shift_id?: number;
    name?: string;
    start?: string;
    end?: string;
    color?: string;
    notes?: string;
}

interface Props {
    employees: Employee[];
    shifts: Shift[];
    schedulesData: Record<number, Record<string, ScheduleEntry>>;
    selectedMonth: string;
    monthOptions: Array<{ value: string; label: string }>;
}

export default function Index({ employees: initialEmployees, shifts: initialShifts, schedulesData: initialSchedulesData, selectedMonth, monthOptions }: Props) {
    const tRoute = useTenantRoute();

    // State
    const [employees, setEmployees] = useState<Employee[]>(initialEmployees);
    const [shifts, setShifts] = useState<Shift[]>(initialShifts);
    const [schedulesData, setSchedulesData] = useState(initialSchedulesData);

    // Modal states
    const [scheduleModalOpen, setScheduleModalOpen] = useState(false);
    const [employeesModalOpen, setEmployeesModalOpen] = useState(false);
    const [shiftsModalOpen, setShiftsModalOpen] = useState(false);

    // Schedule form
    const [scheduleForm, setScheduleForm] = useState({
        id: null as number | null,
        employee_id: 0,
        employee_name: '',
        schedule_date: '',
        schedule_type: 'shift' as 'shift' | 'custom' | 'dayoff',
        shift_id: '',
        custom_start: '09:00',
        custom_end: '17:00',
        notes: '',
    });

    // Employee form
    const [employeeForm, setEmployeeForm] = useState({ id: null as number | null, name: '', is_active: true });
    const [showEmployeeForm, setShowEmployeeForm] = useState(false);

    // Shift form
    const [shiftForm, setShiftForm] = useState({ id: null as number | null, name: '', start_time: '09:00', end_time: '17:00', color_code: '#3b82f6' });
    const [showShiftForm, setShowShiftForm] = useState(false);

    // Calendar data
    const calendarData = useMemo(() => {
        const [year, month] = selectedMonth.split('-').map(Number);
        const daysInMonth = new Date(year, month, 0).getDate();
        const days: Array<{ day: number; date: string; dayName: string }> = [];

        for (let d = 1; d <= daysInMonth; d++) {
            const date = new Date(year, month - 1, d);
            const dateStr = `${year}-${String(month).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
            const dayName = date.toLocaleDateString('es-ES', { weekday: 'short' });
            days.push({ day: d, date: dateStr, dayName });
        }

        // Group by weeks (Mon-Sun)
        const weeks: Array<typeof days> = [];
        let currentWeek: typeof days = [];

        days.forEach((d, index) => {
            const date = new Date(d.date);
            const dayOfWeek = date.getDay();

            if (index === 0 || dayOfWeek === 1) {
                if (currentWeek.length > 0) {
                    weeks.push(currentWeek);
                }
                currentWeek = [];
            }
            currentWeek.push(d);

            if (index === days.length - 1) {
                weeks.push(currentWeek);
            }
        });

        return { weeks };
    }, [selectedMonth]);

    // Open schedule modal
    const openScheduleModal = (employeeId: number, employeeName: string, dateKey: string) => {
        const existing = schedulesData[employeeId]?.[dateKey];

        setScheduleForm({
            id: existing?.id ?? null,
            employee_id: employeeId,
            employee_name: employeeName,
            schedule_date: dateKey,
            schedule_type: existing?.is_day_off ? 'dayoff' : (existing?.shift_id ? 'shift' : (existing ? 'custom' : 'shift')),
            shift_id: existing?.shift_id?.toString() ?? '',
            custom_start: existing?.start ?? '09:00',
            custom_end: existing?.end ?? '17:00',
            notes: existing?.notes ?? '',
        });
        setScheduleModalOpen(true);
    };

    // Save schedule
    const handleSaveSchedule = async () => {
        try {
            const response = await window.axios.post(tRoute('schedules.store'), {
                id: scheduleForm.id,
                employee_id: scheduleForm.employee_id,
                schedule_date: scheduleForm.schedule_date,
                schedule_type: scheduleForm.schedule_type,
                shift_id: scheduleForm.schedule_type === 'shift' ? scheduleForm.shift_id : null,
                custom_start: scheduleForm.schedule_type === 'custom' ? scheduleForm.custom_start : null,
                custom_end: scheduleForm.schedule_type === 'custom' ? scheduleForm.custom_end : null,
                notes: scheduleForm.notes || null,
            });

            if (response.data.success) {
                toast.success(response.data.message);

                // Update local state
                const newSchedule = response.data.data;
                const shift = shifts.find(s => s.id === Number(scheduleForm.shift_id));

                setSchedulesData(prev => ({
                    ...prev,
                    [scheduleForm.employee_id]: {
                        ...prev[scheduleForm.employee_id],
                        [scheduleForm.schedule_date]: scheduleForm.schedule_type === 'dayoff' ? {
                            id: newSchedule.id,
                            is_day_off: true,
                            notes: scheduleForm.notes,
                        } : {
                            id: newSchedule.id,
                            is_day_off: false,
                            shift_id: scheduleForm.schedule_type === 'shift' ? Number(scheduleForm.shift_id) : undefined,
                            name: scheduleForm.schedule_type === 'shift' ? shift?.name : 'Personalizado',
                            start: scheduleForm.schedule_type === 'shift' ? shift?.start_time : scheduleForm.custom_start,
                            end: scheduleForm.schedule_type === 'shift' ? shift?.end_time : scheduleForm.custom_end,
                            color: shift?.color_code ?? '#9ca3af',
                            notes: scheduleForm.notes,
                        },
                    },
                }));

                setScheduleModalOpen(false);
            }
        } catch (error: any) {
            toast.error(error.response?.data?.message || 'Error al guardar horario');
        }
    };

    // Delete schedule
    const handleDeleteSchedule = async () => {
        if (!scheduleForm.id) return;

        try {
            const response = await window.axios.delete(tRoute('schedules.destroy', { schedule: scheduleForm.id }));

            if (response.data.success) {
                toast.success(response.data.message);

                // Remove from local state
                setSchedulesData(prev => {
                    const updated = { ...prev };
                    if (updated[scheduleForm.employee_id]) {
                        delete updated[scheduleForm.employee_id][scheduleForm.schedule_date];
                    }
                    return updated;
                });

                setScheduleModalOpen(false);
            }
        } catch (error: any) {
            toast.error(error.response?.data?.message || 'Error al eliminar horario');
        }
    };

    // Fetch employees
    const fetchEmployees = async () => {
        try {
            const response = await window.axios.get(tRoute('employees.index'));
            if (response.data.success) {
                setEmployees(response.data.data);
            }
        } catch (error) {
            toast.error('Error al cargar empleados');
        }
    };

    // Save employee
    const handleSaveEmployee = async () => {
        try {
            if (employeeForm.id) {
                const response = await window.axios.put(tRoute('employees.update', { employee: employeeForm.id }), employeeForm);
                if (response.data.success) {
                    toast.success(response.data.message);
                    fetchEmployees();
                    setShowEmployeeForm(false);
                }
            } else {
                const response = await window.axios.post(tRoute('employees.store'), employeeForm);
                if (response.data.success) {
                    toast.success(response.data.message);
                    fetchEmployees();
                    setShowEmployeeForm(false);
                }
            }
        } catch (error: any) {
            toast.error(error.response?.data?.message || 'Error al guardar empleado');
        }
    };

    // Delete employee
    const handleDeleteEmployee = async () => {
        if (!employeeForm.id) return;

        try {
            const response = await window.axios.delete(tRoute('employees.destroy', { employee: employeeForm.id }));
            if (response.data.success) {
                toast.success(response.data.message);
                fetchEmployees();
                setShowEmployeeForm(false);
            }
        } catch (error: any) {
            toast.error(error.response?.data?.message || 'Error al eliminar empleado');
        }
    };

    // Fetch shifts
    const fetchShifts = async () => {
        try {
            const response = await window.axios.get(tRoute('shifts.index'));
            if (response.data.success) {
                setShifts(response.data.data);
            }
        } catch (error) {
            toast.error('Error al cargar turnos');
        }
    };

    // Save shift
    const handleSaveShift = async () => {
        try {
            if (shiftForm.id) {
                const response = await window.axios.put(tRoute('shifts.update', { shift: shiftForm.id }), shiftForm);
                if (response.data.success) {
                    toast.success(response.data.message);
                    fetchShifts();
                    setShowShiftForm(false);
                }
            } else {
                const response = await window.axios.post(tRoute('shifts.store'), shiftForm);
                if (response.data.success) {
                    toast.success(response.data.message);
                    fetchShifts();
                    setShowShiftForm(false);
                }
            }
        } catch (error: any) {
            toast.error(error.response?.data?.message || 'Error al guardar turno');
        }
    };

    // Delete shift
    const handleDeleteShift = async () => {
        if (!shiftForm.id) return;

        try {
            const response = await window.axios.delete(tRoute('shifts.destroy', { shift: shiftForm.id }));
            if (response.data.success) {
                toast.success(response.data.message);
                fetchShifts();
                setShowShiftForm(false);
            }
        } catch (error: any) {
            toast.error(error.response?.data?.message || 'Error al eliminar turno');
        }
    };

    const formatDate = (dateStr: string) => {
        return new Date(dateStr + 'T00:00:00').toLocaleDateString('es-ES', {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Gestión de Horarios" />

            <div className="p-6 max-w-full">
                {/* Header */}
                <div className="flex flex-wrap items-center justify-between gap-4 mb-6">
                    <h1 className="text-2xl font-bold text-gray-900 flex items-center gap-2">
                        <Calendar className="w-7 h-7 text-primary" />
                        Gestión de Horarios y Turnos
                    </h1>

                    <div className="flex items-center gap-3">
                        <button
                            onClick={() => { setEmployeesModalOpen(true); fetchEmployees(); }}
                            className="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 flex items-center gap-2"
                        >
                            <Users className="w-4 h-4" />
                            Gestionar Empleados
                        </button>
                        <button
                            onClick={() => { setShiftsModalOpen(true); fetchShifts(); }}
                            className="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 flex items-center gap-2"
                        >
                            <Clock className="w-4 h-4" />
                            Definir Turnos
                        </button>

                        <select
                            value={selectedMonth}
                            onChange={(e) => window.location.href = tRoute('schedules.index') + '?month=' + e.target.value}
                            className="px-4 py-2 rounded-lg border border-gray-300 bg-white"
                        >
                            {monthOptions.map(opt => (
                                <option key={opt.value} value={opt.value}>{opt.label}</option>
                            ))}
                        </select>
                    </div>
                </div>

                {/* Calendar */}
                {employees.length === 0 ? (
                    <div className="bg-white rounded-xl shadow-sm border p-12 text-center">
                        <Users className="w-16 h-16 mx-auto text-gray-300 mb-4" />
                        <p className="text-gray-500 text-lg">No hay empleados activos para mostrar horarios.</p>
                        <button
                            onClick={() => { setEmployeesModalOpen(true); fetchEmployees(); }}
                            className="mt-4 px-4 py-2 bg-primary text-white rounded-lg"
                        >
                            Agregar Empleados
                        </button>
                    </div>
                ) : (
                    <div className="space-y-6">
                        {calendarData.weeks.map((week, weekIndex) => (
                            <div key={weekIndex} className="bg-white rounded-xl shadow-sm border overflow-hidden">
                                <div className="bg-gray-50 px-4 py-2 border-b">
                                    <h3 className="font-semibold text-gray-700">
                                        Días {week[0].day} al {week[week.length - 1].day}
                                    </h3>
                                </div>
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-max">
                                        <thead>
                                            <tr className="bg-gray-50">
                                                <th className="px-4 py-3 text-left text-sm font-medium text-gray-700 sticky left-0 bg-gray-50 z-10 min-w-[150px]">
                                                    Empleado
                                                </th>
                                                {week.map(d => (
                                                    <th key={d.date} className="px-2 py-3 text-center text-sm font-medium text-gray-600 min-w-[100px]">
                                                        <div className="font-bold">{d.day}</div>
                                                        <div className="text-xs text-gray-400 capitalize">{d.dayName}</div>
                                                    </th>
                                                ))}
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-100">
                                            {employees.map(emp => (
                                                <tr key={emp.id} className="hover:bg-gray-50/50">
                                                    <td className="px-4 py-3 text-sm font-medium text-gray-900 sticky left-0 bg-white z-10">
                                                        {emp.name}
                                                    </td>
                                                    {week.map(d => {
                                                        const schedule = schedulesData[emp.id]?.[d.date];

                                                        return (
                                                            <td
                                                                key={d.date}
                                                                onClick={() => openScheduleModal(emp.id, emp.name, d.date)}
                                                                className="px-2 py-2 text-center cursor-pointer hover:bg-primary/5 transition-colors"
                                                            >
                                                                {schedule ? (
                                                                    schedule.is_day_off ? (
                                                                        <div className="flex flex-col items-center">
                                                                            <span className="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-700">
                                                                                <Palmtree className="w-3 h-3" />
                                                                                Libre
                                                                            </span>
                                                                        </div>
                                                                    ) : (
                                                                        <div className="flex flex-col items-center gap-1">
                                                                            <span
                                                                                className="inline-block px-2 py-1 rounded-full text-xs font-medium text-white truncate max-w-full"
                                                                                style={{ backgroundColor: schedule.color }}
                                                                            >
                                                                                {schedule.name}
                                                                            </span>
                                                                            <span className="text-[10px] text-gray-500">
                                                                                {schedule.start} - {schedule.end}
                                                                            </span>
                                                                        </div>
                                                                    )
                                                                ) : (
                                                                    <span className="text-xs text-gray-300">—</span>
                                                                )}
                                                            </td>
                                                        );
                                                    })}
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {/* Schedule Modal */}
            <Dialog open={scheduleModalOpen} onOpenChange={setScheduleModalOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            {scheduleForm.id ? 'Editar/Eliminar Horario' : 'Asignar Horario'}
                        </DialogTitle>
                    </DialogHeader>

                    <div className="space-y-4 py-4">
                        <div className="bg-primary/5 px-4 py-3 rounded-lg">
                            <p className="text-sm"><strong>Empleado:</strong> {scheduleForm.employee_name}</p>
                            <p className="text-sm"><strong>Fecha:</strong> {formatDate(scheduleForm.schedule_date)}</p>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">Tipo de Asignación</label>
                            <div className="flex gap-4">
                                {[
                                    { value: 'shift', label: 'Turno Fijo', icon: Clock },
                                    { value: 'custom', label: 'Personalizado', icon: Edit2 },
                                    { value: 'dayoff', label: 'Día Libre 🌴', icon: Sun },
                                ].map(opt => (
                                    <label
                                        key={opt.value}
                                        className={`flex-1 flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-colors ${
                                            scheduleForm.schedule_type === opt.value
                                                ? 'border-primary bg-primary/5 text-primary'
                                                : 'border-gray-200 hover:border-gray-300'
                                        }`}
                                    >
                                        <input
                                            type="radio"
                                            name="schedule_type"
                                            value={opt.value}
                                            checked={scheduleForm.schedule_type === opt.value}
                                            onChange={(e) => setScheduleForm(prev => ({ ...prev, schedule_type: e.target.value as any }))}
                                            className="hidden"
                                        />
                                        <span className="text-sm">{opt.label}</span>
                                    </label>
                                ))}
                            </div>
                        </div>

                        {scheduleForm.schedule_type === 'shift' && (
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">Seleccionar Turno</label>
                                <select
                                    value={scheduleForm.shift_id}
                                    onChange={(e) => setScheduleForm(prev => ({ ...prev, shift_id: e.target.value }))}
                                    className="w-full px-3 py-2 border rounded-lg"
                                >
                                    <option value="">-- Seleccione un Turno --</option>
                                    {shifts.map(s => (
                                        <option key={s.id} value={s.id}>
                                            {s.name} ({s.start_time} - {s.end_time})
                                        </option>
                                    ))}
                                </select>
                            </div>
                        )}

                        {scheduleForm.schedule_type === 'custom' && (
                            <div className="flex gap-4">
                                <div className="flex-1">
                                    <label className="block text-sm font-medium text-gray-700 mb-2">Inicio</label>
                                    <input
                                        type="time"
                                        value={scheduleForm.custom_start}
                                        onChange={(e) => setScheduleForm(prev => ({ ...prev, custom_start: e.target.value }))}
                                        className="w-full px-3 py-2 border rounded-lg"
                                    />
                                </div>
                                <div className="flex-1">
                                    <label className="block text-sm font-medium text-gray-700 mb-2">Fin</label>
                                    <input
                                        type="time"
                                        value={scheduleForm.custom_end}
                                        onChange={(e) => setScheduleForm(prev => ({ ...prev, custom_end: e.target.value }))}
                                        className="w-full px-3 py-2 border rounded-lg"
                                    />
                                </div>
                            </div>
                        )}

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">Notas (Opcional)</label>
                            <textarea
                                value={scheduleForm.notes}
                                onChange={(e) => setScheduleForm(prev => ({ ...prev, notes: e.target.value }))}
                                className="w-full px-3 py-2 border rounded-lg"
                                rows={2}
                            />
                        </div>
                    </div>

                    <DialogFooter className="flex gap-2">
                        {scheduleForm.id && (
                            <button
                                onClick={handleDeleteSchedule}
                                className="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600"
                            >
                                Eliminar
                            </button>
                        )}
                        <button
                            onClick={() => setScheduleModalOpen(false)}
                            className="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200"
                        >
                            Cancelar
                        </button>
                        <button
                            onClick={handleSaveSchedule}
                            className="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90"
                        >
                            Guardar
                        </button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Employees Modal */}
            <Dialog open={employeesModalOpen} onOpenChange={setEmployeesModalOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Users className="w-5 h-5" />
                            Gestionar Empleados
                        </DialogTitle>
                    </DialogHeader>

                    {!showEmployeeForm ? (
                        <div className="py-4">
                            <div className="max-h-[300px] overflow-y-auto">
                                {employees.length === 0 ? (
                                    <p className="text-center text-gray-500 py-8">No hay empleados registrados.</p>
                                ) : (
                                    <table className="w-full">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-4 py-2 text-left text-sm font-medium text-gray-600">Nombre</th>
                                                <th className="px-4 py-2 text-left text-sm font-medium text-gray-600">Estado</th>
                                                <th className="px-4 py-2 text-right text-sm font-medium text-gray-600">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {employees.map(emp => (
                                                <tr key={emp.id}>
                                                    <td className="px-4 py-2 text-sm">{emp.name}</td>
                                                    <td className="px-4 py-2">
                                                        <span className={`text-xs px-2 py-1 rounded-full ${emp.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'}`}>
                                                            {emp.is_active ? 'Activo' : 'Inactivo'}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-2 text-right">
                                                        <button
                                                            onClick={() => {
                                                                setEmployeeForm({ id: emp.id, name: emp.name, is_active: emp.is_active });
                                                                setShowEmployeeForm(true);
                                                            }}
                                                            className="text-primary hover:underline text-sm"
                                                        >
                                                            Editar
                                                        </button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                )}
                            </div>

                            <button
                                onClick={() => {
                                    setEmployeeForm({ id: null, name: '', is_active: true });
                                    setShowEmployeeForm(true);
                                }}
                                className="mt-4 w-full px-4 py-2 bg-primary text-white rounded-lg flex items-center justify-center gap-2"
                            >
                                <Plus className="w-4 h-4" />
                                Agregar Empleado
                            </button>
                        </div>
                    ) : (
                        <div className="py-4 space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">Nombre</label>
                                <input
                                    type="text"
                                    value={employeeForm.name}
                                    onChange={(e) => setEmployeeForm(prev => ({ ...prev, name: e.target.value }))}
                                    className="w-full px-3 py-2 border rounded-lg"
                                    placeholder="Nombre del empleado"
                                />
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">Estado</label>
                                <div className="flex gap-4">
                                    <label className={`flex-1 px-4 py-2 rounded-lg border cursor-pointer text-center ${employeeForm.is_active ? 'border-green-500 bg-green-50 text-green-700' : 'border-gray-200'}`}>
                                        <input
                                            type="radio"
                                            checked={employeeForm.is_active}
                                            onChange={() => setEmployeeForm(prev => ({ ...prev, is_active: true }))}
                                            className="hidden"
                                        />
                                        Activo
                                    </label>
                                    <label className={`flex-1 px-4 py-2 rounded-lg border cursor-pointer text-center ${!employeeForm.is_active ? 'border-gray-500 bg-gray-50 text-gray-700' : 'border-gray-200'}`}>
                                        <input
                                            type="radio"
                                            checked={!employeeForm.is_active}
                                            onChange={() => setEmployeeForm(prev => ({ ...prev, is_active: false }))}
                                            className="hidden"
                                        />
                                        Inactivo
                                    </label>
                                </div>
                            </div>

                            <div className="flex gap-2 pt-4">
                                <button
                                    onClick={() => setShowEmployeeForm(false)}
                                    className="flex-1 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg"
                                >
                                    Cancelar
                                </button>
                                {employeeForm.id && (
                                    <button
                                        onClick={handleDeleteEmployee}
                                        className="px-4 py-2 bg-red-500 text-white rounded-lg"
                                    >
                                        <Trash2 className="w-4 h-4" />
                                    </button>
                                )}
                                <button
                                    onClick={handleSaveEmployee}
                                    className="flex-1 px-4 py-2 bg-primary text-white rounded-lg"
                                >
                                    {employeeForm.id ? 'Actualizar' : 'Crear'}
                                </button>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            {/* Shifts Modal */}
            <Dialog open={shiftsModalOpen} onOpenChange={setShiftsModalOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Clock className="w-5 h-5" />
                            Definir Turnos
                        </DialogTitle>
                    </DialogHeader>

                    {!showShiftForm ? (
                        <div className="py-4">
                            <div className="max-h-[300px] overflow-y-auto">
                                {shifts.length === 0 ? (
                                    <p className="text-center text-gray-500 py-8">No hay turnos definidos.</p>
                                ) : (
                                    <table className="w-full">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-4 py-2 text-left text-sm font-medium text-gray-600">Nombre</th>
                                                <th className="px-4 py-2 text-left text-sm font-medium text-gray-600">Horario</th>
                                                <th className="px-4 py-2 text-left text-sm font-medium text-gray-600">Color</th>
                                                <th className="px-4 py-2 text-right text-sm font-medium text-gray-600">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {shifts.map(s => (
                                                <tr key={s.id}>
                                                    <td className="px-4 py-2 text-sm">{s.name}</td>
                                                    <td className="px-4 py-2 text-sm text-gray-600">{s.start_time} - {s.end_time}</td>
                                                    <td className="px-4 py-2">
                                                        <div className="w-6 h-6 rounded-full" style={{ backgroundColor: s.color_code }} />
                                                    </td>
                                                    <td className="px-4 py-2 text-right">
                                                        <button
                                                            onClick={() => {
                                                                setShiftForm({ id: s.id, name: s.name, start_time: s.start_time, end_time: s.end_time, color_code: s.color_code });
                                                                setShowShiftForm(true);
                                                            }}
                                                            className="text-primary hover:underline text-sm"
                                                        >
                                                            Editar
                                                        </button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                )}
                            </div>

                            <button
                                onClick={() => {
                                    setShiftForm({ id: null, name: '', start_time: '09:00', end_time: '17:00', color_code: '#3b82f6' });
                                    setShowShiftForm(true);
                                }}
                                className="mt-4 w-full px-4 py-2 bg-primary text-white rounded-lg flex items-center justify-center gap-2"
                            >
                                <Plus className="w-4 h-4" />
                                Agregar Turno
                            </button>
                        </div>
                    ) : (
                        <div className="py-4 space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">Nombre</label>
                                <input
                                    type="text"
                                    value={shiftForm.name}
                                    onChange={(e) => setShiftForm(prev => ({ ...prev, name: e.target.value }))}
                                    className="w-full px-3 py-2 border rounded-lg"
                                    placeholder="Ej: Turno Mañana"
                                />
                            </div>

                            <div className="flex gap-4">
                                <div className="flex-1">
                                    <label className="block text-sm font-medium text-gray-700 mb-2">Inicio</label>
                                    <input
                                        type="time"
                                        value={shiftForm.start_time}
                                        onChange={(e) => setShiftForm(prev => ({ ...prev, start_time: e.target.value }))}
                                        className="w-full px-3 py-2 border rounded-lg"
                                    />
                                </div>
                                <div className="flex-1">
                                    <label className="block text-sm font-medium text-gray-700 mb-2">Fin</label>
                                    <input
                                        type="time"
                                        value={shiftForm.end_time}
                                        onChange={(e) => setShiftForm(prev => ({ ...prev, end_time: e.target.value }))}
                                        className="w-full px-3 py-2 border rounded-lg"
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">Color</label>
                                <input
                                    type="color"
                                    value={shiftForm.color_code}
                                    onChange={(e) => setShiftForm(prev => ({ ...prev, color_code: e.target.value }))}
                                    className="w-16 h-10 rounded-lg cursor-pointer"
                                />
                            </div>

                            <div className="flex gap-2 pt-4">
                                <button
                                    onClick={() => setShowShiftForm(false)}
                                    className="flex-1 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg"
                                >
                                    Cancelar
                                </button>
                                {shiftForm.id && (
                                    <button
                                        onClick={handleDeleteShift}
                                        className="px-4 py-2 bg-red-500 text-white rounded-lg"
                                    >
                                        <Trash2 className="w-4 h-4" />
                                    </button>
                                )}
                                <button
                                    onClick={handleSaveShift}
                                    className="flex-1 px-4 py-2 bg-primary text-white rounded-lg"
                                >
                                    {shiftForm.id ? 'Actualizar' : 'Crear'}
                                </button>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </AuthenticatedLayout>
    );
}
