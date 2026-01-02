<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use App\Services\CurrentTenant;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    private const ON_TIME_LIMIT = '09:00:00';

    public function __construct(
        protected CurrentTenant $currentTenant
    ) {}

    /**
     * Display attendance page with KPIs and history.
     */
    public function index(Request $request): Response
    {
        $tenant = $this->currentTenant->get();
        $user = $request->user();
        $selectedMonth = $request->get('month', date('Y-m'));

        if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
            $selectedMonth = date('Y-m');
        }

        $monthStart = Carbon::parse($selectedMonth . '-01')->startOfMonth();
        $monthEnd = Carbon::parse($selectedMonth . '-01')->endOfMonth();

        // KPIs for current user
        $totalDays = Attendance::where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->whereNotNull('check_in')
            ->whereBetween('check_in', [$monthStart, $monthEnd])
            ->count();

        $onTime = Attendance::where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->whereNotNull('check_in')
            ->whereBetween('check_in', [$monthStart, $monthEnd])
            ->whereRaw("TIME(check_in) <= ?", [self::ON_TIME_LIMIT])
            ->count();

        $completeDays = Attendance::where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->whereNotNull('check_in')
            ->whereNotNull('lunch_out')
            ->whereNotNull('lunch_in')
            ->whereNotNull('check_out')
            ->whereBetween('check_in', [$monthStart, $monthEnd])
            ->count();

        $punctuality = $totalDays > 0 ? round(($onTime / $totalDays) * 100) : 0;

        // History records
        $records = Attendance::where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->whereBetween('check_in', [$monthStart, $monthEnd])
            ->orderByDesc('check_in')
            ->limit(50)
            ->get();

        // Month options
        $monthOptions = [];
        $months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                   'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        for ($i = 0; $i < 12; $i++) {
            $date = Carbon::now()->subMonths($i);
            $monthOptions[] = [
                'value' => $date->format('Y-m'),
                'label' => $months[$date->month - 1] . ' ' . $date->year,
            ];
        }

        // Today's status for current user
        $todayRecord = Attendance::where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->whereDate('check_in', Carbon::today())
            ->first();

        return Inertia::render('Tenant/Attendance/Index', [
            'kpis' => [
                'total_days' => $totalDays,
                'on_time' => $onTime,
                'complete_days' => $completeDays,
                'punctuality' => $punctuality,
            ],
            'records' => $records,
            'selectedMonth' => $selectedMonth,
            'monthOptions' => $monthOptions,
            'todayStatus' => $todayRecord ? [
                'id' => $todayRecord->id,
                'check_in' => $todayRecord->check_in?->format('H:i:s'),
                'lunch_out' => $todayRecord->lunch_out?->format('H:i:s'),
                'lunch_in' => $todayRecord->lunch_in?->format('H:i:s'),
                'check_out' => $todayRecord->check_out?->format('H:i:s'),
            ] : null,
            'onTimeLimit' => self::ON_TIME_LIMIT,
        ]);
    }

    /**
     * Check RUT and return user info.
     */
    public function checkRut(Request $request): JsonResponse
    {
        $rut = preg_replace('/[^0-9kK]/', '', $request->input('rut', ''));

        $user = User::where('rut', $rut)->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'RUT no encontrado. Verifica los datos.'
            ]);
        }

        return response()->json([
            'status' => 'success',
            'user_id' => $user->id,
            'name' => $user->name,
        ]);
    }

    /**
     * Check PIN and return today's attendance status.
     */
    public function checkPin(Request $request): JsonResponse
    {
        $userId = $request->input('user_id');
        $pin = $request->input('pin');
        $tenant = $this->currentTenant->get();

        $user = User::find($userId);

        if (!$user || !Hash::check($pin, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'PIN/Clave incorrecta. Intenta de nuevo.'
            ]);
        }

        // Get today's attendance record
        $todayRecord = Attendance::where('tenant_id', $tenant->id)
            ->where('user_id', $userId)
            ->whereDate('check_in', Carbon::today())
            ->first();

        return response()->json([
            'status' => 'success',
            'user_name' => $user->name,
            'attendance_id' => $todayRecord?->id,
            'attendance_status' => [
                'check_in' => $todayRecord?->check_in?->format('H:i:s'),
                'lunch_out' => $todayRecord?->lunch_out?->format('H:i:s'),
                'lunch_in' => $todayRecord?->lunch_in?->format('H:i:s'),
                'check_out' => $todayRecord?->check_out?->format('H:i:s'),
            ],
        ]);
    }

    /**
     * Register an attendance event.
     */
    public function registerEvent(Request $request): JsonResponse
    {
        $userId = $request->input('user_id');
        $eventType = $request->input('event_type');
        $attendanceId = $request->input('attendance_id');
        $tenant = $this->currentTenant->get();

        $messages = [
            'check_in' => '✅ Entrada registrada correctamente. ¡Bienvenido/a!',
            'lunch_out' => '🍔 Salida a Colación registrada. ¡Que disfrutes!',
            'lunch_in' => '💼 Entrada a Jornada registrada. ¡De vuelta al trabajo!',
            'check_out' => '👋 Salida de Turno registrada. ¡Hasta mañana!',
        ];

        // Check_in creates new record
        if ($eventType === 'check_in') {
            $existing = Attendance::where('tenant_id', $tenant->id)
                ->where('user_id', $userId)
                ->whereDate('check_in', Carbon::today())
                ->first();

            if ($existing) {
                return response()->json([
                    'status' => 'error',
                    'message' => '¡Error! Ya registraste tu Entrada de hoy.'
                ]);
            }

            $record = Attendance::create([
                'tenant_id' => $tenant->id,
                'user_id' => $userId,
                'check_in' => Carbon::now(),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => $messages[$eventType],
                'attendance_id' => $record->id,
            ]);
        }

        // Other events update existing record
        if (!$attendanceId) {
            return response()->json([
                'status' => 'error',
                'message' => '❌ Debe registrar la Entrada antes de cualquier otro evento.'
            ]);
        }

        $record = Attendance::find($attendanceId);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => '❌ Registro no encontrado.'
            ]);
        }

        if ($record->$eventType !== null) {
            return response()->json([
                'status' => 'error',
                'message' => "¡Error! Este evento ya fue registrado hoy."
            ]);
        }

        $record->update([$eventType => Carbon::now()]);

        return response()->json([
            'status' => 'success',
            'message' => $messages[$eventType],
        ]);
    }
}
