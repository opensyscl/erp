import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useTenantRoute } from '@/Hooks/useTenantRoute';
import { useState } from 'react';
import { toast } from 'sonner';
import axios from 'axios';
import {
    Clock,
    CheckCircle,
    Calendar,
    TrendingUp,
    Coffee,
    LogIn,
    LogOut,
    User
} from 'lucide-react';

interface AttendanceRecord {
    id: number;
    check_in: string | null;
    lunch_out: string | null;
    lunch_in: string | null;
    check_out: string | null;
}

interface Props {
    kpis: {
        total_days: number;
        on_time: number;
        complete_days: number;
        punctuality: number;
    };
    records: AttendanceRecord[];
    selectedMonth: string;
    monthOptions: { value: string; label: string }[];
    todayStatus: {
        id: number;
        check_in: string | null;
        lunch_out: string | null;
        lunch_in: string | null;
        check_out: string | null;
    } | null;
    onTimeLimit: string;
}

type KioskStep = 'rut' | 'pin' | 'events';
type EventType = 'check_in' | 'lunch_out' | 'lunch_in' | 'check_out';

export default function Index({ kpis, records, selectedMonth, monthOptions, todayStatus, onTimeLimit }: Props) {
    const tRoute = useTenantRoute();

    // Kiosk state
    const [kioskStep, setKioskStep] = useState<KioskStep>('rut');
    const [rut, setRut] = useState('');
    const [pin, setPin] = useState('');
    const [kioskUserId, setKioskUserId] = useState<number | null>(null);
    const [kioskUserName, setKioskUserName] = useState('');
    const [attendanceId, setAttendanceId] = useState<number | null>(null);
    const [attendanceStatus, setAttendanceStatus] = useState<Record<EventType, string | null>>({
        check_in: null,
        lunch_out: null,
        lunch_in: null,
        check_out: null,
    });
    const [loading, setLoading] = useState(false);

    const handleMonthChange = (month: string) => {
        router.get(tRoute('attendance.index'), { month }, { preserveState: true });
    };

    // Format RUT as user types
    const formatRut = (value: string) => {
        const cleaned = value.replace(/[^0-9kK]/g, '');
        if (cleaned.length <= 1) return cleaned;
        const body = cleaned.slice(0, -1);
        const dv = cleaned.slice(-1);
        const formatted = body.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return `${formatted}-${dv}`;
    };

    const handleRutSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        try {
            const response = await axios.post(tRoute('attendance.check-rut'), { rut });
            if (response.data.status === 'success') {
                setKioskUserId(response.data.user_id);
                setKioskUserName(response.data.name);
                setKioskStep('pin');
            } else {
                toast.error(response.data.message);
            }
        } catch (error) {
            toast.error('Error al verificar RUT');
        } finally {
            setLoading(false);
        }
    };

    const handlePinSubmit = async () => {
        if (pin.length < 4) return;
        setLoading(true);
        try {
            const response = await axios.post(tRoute('attendance.check-pin'), {
                user_id: kioskUserId,
                pin,
            });
            if (response.data.status === 'success') {
                setKioskUserName(response.data.user_name);
                setAttendanceId(response.data.attendance_id);
                setAttendanceStatus(response.data.attendance_status);
                setKioskStep('events');
            } else {
                toast.error(response.data.message);
                setPin('');
            }
        } catch (error) {
            toast.error('Error al verificar PIN');
        } finally {
            setLoading(false);
        }
    };

    const handleRegisterEvent = async (eventType: EventType) => {
        setLoading(true);
        try {
            const response = await axios.post(tRoute('attendance.register-event'), {
                user_id: kioskUserId,
                event_type: eventType,
                attendance_id: attendanceId,
            });
            if (response.data.status === 'success') {
                toast.success(response.data.message);
                if (response.data.attendance_id) {
                    setAttendanceId(response.data.attendance_id);
                }
                // Update local status
                setAttendanceStatus(prev => ({
                    ...prev,
                    [eventType]: new Date().toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit', second: '2-digit' }),
                }));
            } else {
                toast.error(response.data.message);
            }
        } catch (error) {
            toast.error('Error al registrar evento');
        } finally {
            setLoading(false);
        }
    };

    const resetKiosk = () => {
        setKioskStep('rut');
        setRut('');
        setPin('');
        setKioskUserId(null);
        setKioskUserName('');
        setAttendanceId(null);
        setAttendanceStatus({ check_in: null, lunch_out: null, lunch_in: null, check_out: null });
    };

    const formatTime = (datetime: string | null) => {
        if (!datetime) return '-';
        const date = new Date(datetime);
        return date.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' });
    };

    const formatDate = (datetime: string | null) => {
        if (!datetime) return '-';
        const date = new Date(datetime);
        return date.toLocaleDateString('es-CL');
    };

    const isLate = (checkIn: string | null) => {
        if (!checkIn) return false;
        const time = new Date(checkIn).toTimeString().slice(0, 8);
        return time > onTimeLimit;
    };

    const KpiCard = ({ title, value, icon: Icon, color = 'text-gray-600', suffix = '' }: { title: string; value: number; icon: any; color?: string; suffix?: string }) => (
        <div className="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
            <div className="flex items-center gap-3">
                <div className={`p-2 rounded-lg bg-gray-100 ${color}`}>
                    <Icon className="w-5 h-5" />
                </div>
                <div>
                    <p className="text-xs text-gray-500">{title}</p>
                    <p className={`text-xl font-bold ${color}`}>{value}{suffix}</p>
                </div>
            </div>
        </div>
    );

    const EventButton = ({ type, label, icon: Icon, disabled }: { type: EventType; label: string; icon: any; disabled: boolean }) => (
        <button
            onClick={() => handleRegisterEvent(type)}
            disabled={disabled || loading}
            className={`flex flex-col items-center justify-center p-4 rounded-xl transition-all ${
                attendanceStatus[type]
                    ? 'bg-green-100 text-green-700 cursor-not-allowed'
                    : disabled
                    ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
                    : 'bg-primary text-white hover:bg-primary/90'
            }`}
        >
            <Icon className="w-6 h-6 mb-1" />
            <span className="text-sm font-medium">{label}</span>
            {attendanceStatus[type] && (
                <span className="text-xs mt-1">{attendanceStatus[type]}</span>
            )}
        </button>
    );

    return (
        <AuthenticatedLayout>
            <Head title="Registro de Asistencias" />

            <div className="py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">⏰ Registro de Asistencias</h1>
                        <p className="text-sm text-gray-500">Registra y monitorea la asistencia de tu equipo</p>
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
                    <KpiCard title="Días Registrados" value={kpis.total_days} icon={Calendar} color="text-blue-600" />
                    <KpiCard title="A Tiempo" value={kpis.on_time} icon={CheckCircle} color="text-green-600" />
                    <KpiCard title="Días Completos" value={kpis.complete_days} icon={Clock} color="text-purple-600" />
                    <KpiCard title="Puntualidad" value={kpis.punctuality} icon={TrendingUp} color={kpis.punctuality >= 90 ? 'text-green-600' : kpis.punctuality >= 70 ? 'text-yellow-600' : 'text-red-600'} suffix="%" />
                </div>

                {/* Kiosk Section */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-100 mb-6">
                    <div className="p-6 border-b border-gray-100">
                        <h2 className="text-lg font-semibold">🖥️ Registro de Asistencia (Kiosk)</h2>
                        <p className="text-sm text-gray-500">Hoy: {new Date().toLocaleDateString('es-CL')}</p>
                    </div>
                    <div className="p-6">
                        {kioskStep === 'rut' && (
                            <form onSubmit={handleRutSubmit} className="max-w-md mx-auto">
                                <label className="block text-sm font-medium text-gray-700 mb-2">Ingresa tu RUT</label>
                                <input
                                    type="text"
                                    value={rut}
                                    onChange={(e) => setRut(formatRut(e.target.value))}
                                    placeholder="12.345.678-9"
                                    className="w-full px-4 py-3 text-lg border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary/20 text-center"
                                    maxLength={12}
                                />
                                <button
                                    type="submit"
                                    disabled={loading || rut.length < 9}
                                    className="w-full mt-4 px-6 py-3 bg-primary text-white rounded-lg font-medium hover:bg-primary/90 disabled:opacity-50"
                                >
                                    {loading ? 'Verificando...' : 'Siguiente'}
                                </button>
                            </form>
                        )}

                        {kioskStep === 'pin' && (
                            <div className="max-w-md mx-auto text-center">
                                <p className="text-sm text-gray-500 mb-2">Hola,</p>
                                <h3 className="text-xl font-bold text-gray-900 mb-4">{kioskUserName}</h3>
                                <label className="block text-sm font-medium text-gray-700 mb-2">Ingresa tu PIN de 4 dígitos</label>

                                <div className="flex justify-center gap-2 mb-4">
                                    {[0, 1, 2, 3].map((i) => (
                                        <div
                                            key={i}
                                            className={`w-12 h-12 rounded-lg border-2 flex items-center justify-center text-xl font-bold ${
                                                pin.length > i ? 'border-primary bg-primary/10' : 'border-gray-200'
                                            }`}
                                        >
                                            {pin.length > i ? '•' : ''}
                                        </div>
                                    ))}
                                </div>

                                <div className="grid grid-cols-3 gap-2 max-w-xs mx-auto mb-4">
                                    {[1, 2, 3, 4, 5, 6, 7, 8, 9, 'clear', 0, 'back'].map((key) => (
                                        <button
                                            key={key}
                                            onClick={() => {
                                                if (key === 'clear') setPin('');
                                                else if (key === 'back') setPin(p => p.slice(0, -1));
                                                else if (pin.length < 4) setPin(p => p + key);
                                            }}
                                            className={`py-3 rounded-lg font-medium ${
                                                typeof key === 'number'
                                                    ? 'bg-gray-100 hover:bg-gray-200 text-xl'
                                                    : 'bg-gray-200 text-sm'
                                            }`}
                                        >
                                            {key === 'clear' ? 'Limpiar' : key === 'back' ? '⌫' : key}
                                        </button>
                                    ))}
                                </div>

                                <div className="flex gap-2">
                                    <button
                                        onClick={resetKiosk}
                                        className="flex-1 px-4 py-2 border border-gray-200 rounded-lg text-gray-600"
                                    >
                                        Cancelar
                                    </button>
                                    <button
                                        onClick={handlePinSubmit}
                                        disabled={loading || pin.length < 4}
                                        className="flex-1 px-4 py-2 bg-primary text-white rounded-lg disabled:opacity-50"
                                    >
                                        {loading ? 'Validando...' : 'Validar PIN'}
                                    </button>
                                </div>
                            </div>
                        )}

                        {kioskStep === 'events' && (
                            <div className="max-w-lg mx-auto text-center">
                                <p className="text-sm text-gray-500 mb-1">Bienvenido/a,</p>
                                <h3 className="text-xl font-bold text-gray-900 mb-4">{kioskUserName}</h3>

                                <div className="grid grid-cols-2 gap-3 mb-4">
                                    <EventButton type="check_in" label="Entrada" icon={LogIn} disabled={!!attendanceStatus.check_in} />
                                    <EventButton type="lunch_out" label="Salida Colación" icon={Coffee} disabled={!attendanceStatus.check_in || !!attendanceStatus.lunch_out} />
                                    <EventButton type="lunch_in" label="Entrada Jornada" icon={LogIn} disabled={!attendanceStatus.lunch_out || !!attendanceStatus.lunch_in} />
                                    <EventButton type="check_out" label="Salida Turno" icon={LogOut} disabled={!attendanceStatus.check_in || !!attendanceStatus.check_out} />
                                </div>

                                <button
                                    onClick={resetKiosk}
                                    className="w-full px-4 py-2 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200"
                                >
                                    Volver a RUT
                                </button>
                            </div>
                        )}
                    </div>
                </div>

                {/* History Table */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <div className="p-6 border-b border-gray-100">
                        <h2 className="text-lg font-semibold">Mi Historial de Asistencia</h2>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left font-medium text-gray-600">Fecha</th>
                                    <th className="px-4 py-3 text-center font-medium text-gray-600">Entrada</th>
                                    <th className="px-4 py-3 text-center font-medium text-gray-600">Salida Col.</th>
                                    <th className="px-4 py-3 text-center font-medium text-gray-600">Entrada Jorn.</th>
                                    <th className="px-4 py-3 text-center font-medium text-gray-600">Salida</th>
                                    <th className="px-4 py-3 text-center font-medium text-gray-600">Puntualidad</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {records.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-12 text-center text-gray-500">
                                            No hay registros para este mes
                                        </td>
                                    </tr>
                                ) : (
                                    records.map((record) => (
                                        <tr key={record.id} className="hover:bg-gray-50">
                                            <td className="px-4 py-3">{formatDate(record.check_in)}</td>
                                            <td className="px-4 py-3 text-center">{formatTime(record.check_in)}</td>
                                            <td className="px-4 py-3 text-center">{formatTime(record.lunch_out)}</td>
                                            <td className="px-4 py-3 text-center">{formatTime(record.lunch_in)}</td>
                                            <td className="px-4 py-3 text-center">{formatTime(record.check_out)}</td>
                                            <td className="px-4 py-3 text-center">
                                                <span className={`px-2 py-1 rounded-full text-xs ${
                                                    isLate(record.check_in)
                                                        ? 'bg-red-100 text-red-700'
                                                        : 'bg-green-100 text-green-700'
                                                }`}>
                                                    {isLate(record.check_in) ? 'Tarde' : 'A Tiempo'}
                                                </span>
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
