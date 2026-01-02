<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\InternalConsumption;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InternalConsumptionController extends Controller
{
    public function index(Request $request)
    {
        $dateStart = $request->input('date_start', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dateEnd = $request->input('date_end', Carbon::now()->endOfMonth()->format('Y-m-d'));

        // Query Base
        $query = InternalConsumption::with(['product', 'user'])
            ->whereBetween('removal_date', [
                Carbon::parse($dateStart)->startOfDay(),
                Carbon::parse($dateEnd)->endOfDay()
            ]);

        // KPIs
        $stats = (clone $query)->select(
            DB::raw('SUM(quantity_removed) as total_units'),
            DB::raw('SUM(quantity_removed * cost_price_at_time) as total_cost_net'),
            DB::raw('SUM(quantity_removed * sale_price_at_time) as total_lost_sales')
        )->first();

        // Top Products
        $topProducts = (clone $query)
            ->select('product_id', DB::raw('SUM(quantity_removed) as total_removed'))
            ->groupBy('product_id')
            ->orderByDesc('total_removed')
            ->limit(5)
            ->with('product:id,name,image_url')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->product->name,
                    'image_url' => $item->product->image_url,
                    'total_removed' => $item->total_removed,
                ];
            });

        // History Table
        $history = (clone $query)
            ->orderBy('removal_date', 'desc')
            ->limit(100) // Reasonable limit for now
            ->get();

        return Inertia::render('Tenant/Outputs/Index', [
            'metrics' => [
                'total_units' => $stats->total_units ?? 0,
                'total_cost_net' => $stats->total_cost_net ?? 0,
                // Costo Bruto (con IVA 19%) - Assuming global tax
                'total_cost_gross' => ($stats->total_cost_net ?? 0) * 1.19,
                'total_lost_sales' => $stats->total_lost_sales ?? 0,
            ],
            'topProducts' => $topProducts,
            'history' => $history,
            'filters' => [
                'date_start' => $dateStart,
                'date_end' => $dateEnd,
            ]
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:255',
        ]);

        return DB::transaction(function () use ($request) {
            $product = Product::lockForUpdate()->find($request->product_id);

            if ($product->stock < $request->quantity) {
                return back()->with('error', "Stock insuficiente. Disponible: {$product->stock}");
            }

            // Decrement Stock
            $product->decrement('stock', $request->quantity);

            // Record Log
            InternalConsumption::create([
                'product_id' => $product->id,
                'user_id' => Auth::id(),
                'quantity_removed' => $request->quantity,
                'cost_price_at_time' => $product->cost_price ?? 0,
                'sale_price_at_time' => $product->price ?? 0,
                'notes' => $request->notes,
                'removal_date' => Carbon::now(),
            ]);

            return back()->with('success', 'Salida registrada correctamente.');
        });
    }

    public function update(Request $request, InternalConsumption $output)
    {
        // We only allow updating metadata, NOT quantity/product to simplify stock integrity
        $validated = $request->validate([
            'notes' => 'nullable|string|max:255',
            'removal_date' => 'nullable|date',
        ]);

        $output->update($validated);

        return back()->with('success', 'Registro actualizado.');
    }

    public function destroy(InternalConsumption $output)
    {
        return DB::transaction(function () use ($output) {
            // Revert Stock
            $product = Product::lockForUpdate()->find($output->product_id);
            if ($product) {
                $product->increment('stock', $output->quantity_removed);
            }

            $output->delete();

            return back()->with('success', 'Registro eliminado y stock repuesto.');
        });
    }

    public function search(Request $request)
    {
        $query = $request->input('query');

        $products = Product::where('name', 'like', "%{$query}%")
            ->orWhere('barcode', 'like', "%{$query}%")
            ->limit(10)
            ->get(['id', 'name', 'barcode', 'stock', 'image_url']);

        return response()->json($products);
    }
}
