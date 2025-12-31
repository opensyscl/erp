<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\Shift;
use App\Services\CurrentTenant;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    public function __construct(
        protected CurrentTenant $currentTenant
    ) {}

    /**
     * Display the schedule calendar.
     */
    public function index(Request $request): Response
    {
        $month = $request->query('month', now()->format('Y-m'));

        // Validate month format
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->format('Y-m');
        }

        $startOfMonth = Carbon::parse($month . '-01')->startOfMonth();
        $endOfMonth = Carbon::parse($month . '-01')->endOfMonth();

        // Get active employees
        $employees = Employee::where('tenant_id', $this->currentTenant->id())
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'is_active']);

        // Get shifts
        $shifts = Shift::where('tenant_id', $this->currentTenant->id())
            ->orderBy('start_time')
            ->get()
            ->map(fn($shift) => [
                'id' => $shift->id,
                'name' => $shift->name,
                'start_time' => $shift->start_time?->format('H:i'),
                'end_time' => $shift->end_time?->format('H:i'),
                'color_code' => $shift->color_code,
            ]);

        // Get schedules for the month
        $schedules = Schedule::with('shift')
            ->where('tenant_id', $this->currentTenant->id())
            ->whereBetween('schedule_date', [$startOfMonth, $endOfMonth])
            ->get();

        // Organize schedules by employee_id -> date
        $schedulesData = [];
        foreach ($schedules as $schedule) {
            $employeeId = $schedule->employee_id;
            $dateKey = $schedule->schedule_date->format('Y-m-d');

            if (!isset($schedulesData[$employeeId])) {
                $schedulesData[$employeeId] = [];
            }

            if ($schedule->is_day_off) {
                $schedulesData[$employeeId][$dateKey] = [
                    'id' => $schedule->id,
                    'is_day_off' => true,
                    'notes' => $schedule->notes,
                ];
            } else {
                $schedulesData[$employeeId][$dateKey] = [
                    'id' => $schedule->id,
                    'is_day_off' => false,
                    'shift_id' => $schedule->shift_id,
                    'name' => $schedule->shift?->name ?? 'Personalizado',
                    'start' => $schedule->custom_start?->format('H:i') ?? $schedule->shift?->start_time?->format('H:i'),
                    'end' => $schedule->custom_end?->format('H:i') ?? $schedule->shift?->end_time?->format('H:i'),
                    'color' => $schedule->shift?->color_code ?? '#9ca3af',
                    'notes' => $schedule->notes,
                ];
            }
        }

        // Generate month options (last 12 months)
        $monthOptions = [];
        for ($i = 0; $i < 12; $i++) {
            $date = now()->subMonths($i);
            $monthOptions[] = [
                'value' => $date->format('Y-m'),
                'label' => ucfirst($date->translatedFormat('F Y')),
            ];
        }

        return Inertia::render('Tenant/Schedules/Index', [
            'employees' => $employees,
            'shifts' => $shifts,
            'schedulesData' => $schedulesData,
            'selectedMonth' => $month,
            'monthOptions' => $monthOptions,
        ]);
    }

    /**
     * Store or update a schedule.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|exists:schedules,id',
            'employee_id' => 'required|exists:employees,id',
            'schedule_date' => 'required|date',
            'schedule_type' => 'required|in:shift,custom,dayoff',
            'shift_id' => 'nullable|exists:shifts,id',
            'custom_start' => 'nullable|date_format:H:i',
            'custom_end' => 'nullable|date_format:H:i',
            'notes' => 'nullable|string|max:500',
        ]);

        // Verify employee belongs to tenant
        $employee = Employee::find($validated['employee_id']);
        if (!$employee || $employee->tenant_id !== $this->currentTenant->id()) {
            return response()->json(['success' => false, 'message' => 'Empleado no válido.'], 403);
        }

        $scheduleData = [
            'tenant_id' => $this->currentTenant->id(),
            'employee_id' => $validated['employee_id'],
            'schedule_date' => $validated['schedule_date'],
            'notes' => $validated['notes'] ?? null,
            'is_day_off' => false,
            'shift_id' => null,
            'custom_start' => null,
            'custom_end' => null,
        ];

        switch ($validated['schedule_type']) {
            case 'dayoff':
                $scheduleData['is_day_off'] = true;
                break;
            case 'shift':
                $scheduleData['shift_id'] = $validated['shift_id'];
                break;
            case 'custom':
                $scheduleData['custom_start'] = $validated['custom_start'];
                $scheduleData['custom_end'] = $validated['custom_end'];
                break;
        }

        if (!empty($validated['id'])) {
            // Update existing
            $schedule = Schedule::find($validated['id']);
            if (!$schedule || $schedule->tenant_id !== $this->currentTenant->id()) {
                return response()->json(['success' => false, 'message' => 'Horario no encontrado.'], 404);
            }
            $schedule->update($scheduleData);
            $message = 'Horario actualizado exitosamente.';
        } else {
            // Create or update by unique constraint
            $schedule = Schedule::updateOrCreate(
                [
                    'employee_id' => $validated['employee_id'],
                    'schedule_date' => $validated['schedule_date'],
                ],
                $scheduleData
            );
            $message = 'Horario asignado exitosamente.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $schedule,
        ]);
    }

    /**
     * Delete a schedule.
     */
    public function destroy(Schedule $schedule): JsonResponse
    {
        if ($schedule->tenant_id !== $this->currentTenant->id()) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
        }

        $schedule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Horario eliminado exitosamente.',
        ]);
    }
}
