<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Services\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function __construct(
        protected CurrentTenant $currentTenant
    ) {}

    /**
     * List all shifts for the current tenant.
     */
    public function index(): JsonResponse
    {
        $shifts = Shift::where('tenant_id', $this->currentTenant->id())
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $shifts->map(fn($shift) => [
                'id' => $shift->id,
                'name' => $shift->name,
                'start_time' => $shift->start_time?->format('H:i'),
                'end_time' => $shift->end_time?->format('H:i'),
                'color_code' => $shift->color_code,
            ]),
        ]);
    }

    /**
     * Create a new shift.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'color_code' => 'required|string|max:7',
        ]);

        $shift = Shift::create([
            'tenant_id' => $this->currentTenant->id(),
            ...$validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Turno creado exitosamente.',
            'data' => $shift,
        ], 201);
    }

    /**
     * Update an existing shift.
     */
    public function update(Request $request, Shift $shift): JsonResponse
    {
        if ($shift->tenant_id !== $this->currentTenant->id()) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'start_time' => 'sometimes|required|date_format:H:i',
            'end_time' => 'sometimes|required|date_format:H:i',
            'color_code' => 'sometimes|required|string|max:7',
        ]);

        $shift->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Turno actualizado exitosamente.',
            'data' => $shift,
        ]);
    }

    /**
     * Delete a shift.
     */
    public function destroy(Shift $shift): JsonResponse
    {
        if ($shift->tenant_id !== $this->currentTenant->id()) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
        }

        $shift->delete();

        return response()->json([
            'success' => true,
            'message' => 'Turno eliminado exitosamente.',
        ]);
    }
}
