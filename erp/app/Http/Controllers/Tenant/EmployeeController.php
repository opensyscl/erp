<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function __construct(
        protected CurrentTenant $currentTenant
    ) {}

    /**
     * List all employees for the current tenant.
     */
    public function index(): JsonResponse
    {
        $employees = Employee::where('tenant_id', $this->currentTenant->id())
            ->orderBy('name')
            ->get(['id', 'name', 'is_active']);

        return response()->json([
            'success' => true,
            'data' => $employees,
        ]);
    }

    /**
     * Create a new employee.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        $employee = Employee::create([
            'tenant_id' => $this->currentTenant->id(),
            'name' => $validated['name'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Empleado creado exitosamente.',
            'data' => $employee,
        ], 201);
    }

    /**
     * Update an existing employee.
     */
    public function update(Request $request, Employee $employee): JsonResponse
    {
        // Ensure employee belongs to current tenant
        if ($employee->tenant_id !== $this->currentTenant->id()) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        $employee->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Empleado actualizado exitosamente.',
            'data' => $employee,
        ]);
    }

    /**
     * Delete an employee.
     */
    public function destroy(Employee $employee): JsonResponse
    {
        // Ensure employee belongs to current tenant
        if ($employee->tenant_id !== $this->currentTenant->id()) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
        }

        $employee->delete();

        return response()->json([
            'success' => true,
            'message' => 'Empleado eliminado exitosamente.',
        ]);
    }
}
