<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Supplier;
use App\Services\CurrentTenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TercerosController extends Controller
{
    private CurrentTenant $currentTenant;

    public function __construct(CurrentTenant $currentTenant)
    {
        $this->currentTenant = $currentTenant;
    }

    public function index(Request $request): Response
    {
        $tenant = $this->currentTenant->get();
        $tab = $request->query('tab', 'suppliers');

        $clients = DB::table('clients')
            ->where('tenant_id', $tenant->id)
            ->select('id', 'name', 'rut', 'address', 'city', 'email', 'phone', 'image', 'created_at')
            ->orderBy('name')
            ->get();

        $suppliers = Supplier::where('tenant_id', $tenant->id)
            ->select('id', 'name', 'rut', 'address', 'email', 'phone', 'created_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($s) {
                $s->city = null;
                $s->image = null;
                return $s;
            });

        return Inertia::render('Tenant/Terceros/Index', [
            'clients' => $clients,
            'suppliers' => $suppliers,
            'activeTab' => $tab,
        ]);
    }

    public function storeClient(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $tenant = $this->currentTenant->get();

        DB::table('clients')->insert([
            'tenant_id' => $tenant->id,
            'name' => $request->input('name'),
            'rut' => $request->input('rut'),
            'address' => $request->input('address'),
            'city' => $request->input('city'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'image' => $request->input('image'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Cliente registrado exitosamente');
    }

    public function updateClient(Request $request, int $id): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $tenant = $this->currentTenant->get();

        DB::table('clients')
            ->where('id', $id)
            ->where('tenant_id', $tenant->id)
            ->update([
                'name' => $request->input('name'),
                'rut' => $request->input('rut'),
                'address' => $request->input('address'),
                'city' => $request->input('city'),
                'email' => $request->input('email'),
                'phone' => $request->input('phone'),
                'image' => $request->input('image'),
                'updated_at' => now(),
            ]);

        return redirect()->back()->with('success', 'Cliente actualizado exitosamente');
    }

    public function storeSupplier(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $tenant = $this->currentTenant->get();

        Supplier::create([
            'tenant_id' => $tenant->id,
            'name' => $request->input('name'),
            'rut' => $request->input('rut'),
            'address' => $request->input('address'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
        ]);

        return redirect()->back()->with('success', 'Proveedor registrado exitosamente');
    }

    public function updateSupplier(Request $request, int $id): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $tenant = $this->currentTenant->get();

        Supplier::where('id', $id)
            ->where('tenant_id', $tenant->id)
            ->update([
                'name' => $request->input('name'),
                'rut' => $request->input('rut'),
                'address' => $request->input('address'),
                'email' => $request->input('email'),
                'phone' => $request->input('phone'),
            ]);

        return redirect()->back()->with('success', 'Proveedor actualizado exitosamente');
    }
}
