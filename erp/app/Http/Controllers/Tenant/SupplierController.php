<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Services\CurrentTenant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function __construct(
        protected CurrentTenant $currentTenant
    ) {}

    /**
     * Display a listing of suppliers.
     */
    public function index(): Response
    {
        $suppliers = Supplier::withCount('products')
            ->orderBy('name')
            ->get();

        return Inertia::render('Tenant/Inventory/Suppliers/Index', [
            'suppliers' => $suppliers,
        ]);
    }

    /**
     * Show the form for creating a new supplier.
     */
    public function create(): Response
    {
        return Inertia::render('Tenant/Inventory/Suppliers/Create');
    }

    /**
     * Store a newly created supplier.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'rut' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
        ]);

        Supplier::create($validated);

        return back()->with('success', 'Proveedor creado.');
    }

    /**
     * Update the specified supplier.
     */
    public function update(Request $request, string $supplierId)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'rut' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $supplier = Supplier::findOrFail($supplierId);
        $supplier->update($validated);

        return back()->with('success', 'Proveedor actualizado.');
    }

    /**
     * Remove the specified supplier.
     */
    public function destroy(string $supplierId)
    {
        $supplier = Supplier::findOrFail($supplierId);

        if ($supplier->products()->count() > 0) {
            return back()->with('error', 'No se puede eliminar un proveedor con productos asociados.');
        }

        $supplier->delete();

        return back()->with('success', 'Proveedor eliminado.');
    }
}
