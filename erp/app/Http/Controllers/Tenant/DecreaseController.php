<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\DecreaseRecord;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DecreaseController extends Controller
{
    public function index(Request $request)
    {
        // Date Filtering
        $selectedMonth = $request->get('month', date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
            $selectedMonth = date('Y-m');
        }

        $startOfMonth = Carbon::parse($selectedMonth . '-01')->startOfMonth();
        $endOfMonth = Carbon::parse($selectedMonth . '-01')->endOfMonth();

        // Base Query (no tenant_id filter - table doesn't have this column)
        $recordsQuery = DecreaseRecord::with(['product', 'supplier'])
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth]);

        // === KPI CALCULATIONS (Legacy replication) ===

        // 1. Pérdida por Costo (Bruta) & 5. Unidades Mermadas Totales
        $totals = (clone $recordsQuery)
            ->selectRaw('SUM(total_cost_loss) as cost_loss, SUM(quantity) as qty')
            ->first();
        $totalCostLoss = $totals->cost_loss ?? 0;
        $totalQuantity = $totals->qty ?? 0;

        // 2. Pérdida Potencial (Margen Bruto)
        $marginLoss = DB::table('decrease_records')
            ->join('products', 'decrease_records.product_id', '=', 'products.id')
            ->whereBetween('decrease_records.created_at', [$startOfMonth, $endOfMonth])
            ->sum(DB::raw('decrease_records.quantity * (products.price - decrease_records.cost_per_unit)'));

        // 3. Pérdida Total (Potencial de Venta)
        $totalPotentialLoss = $totalCostLoss + $marginLoss;

        // 4. Proveedor con Más Pérdida
        $topSupplier = DB::table('decrease_records')
            ->join('suppliers', 'decrease_records.supplier_id', '=', 'suppliers.id')
            ->whereBetween('decrease_records.created_at', [$startOfMonth, $endOfMonth])
            ->select('suppliers.name', DB::raw('SUM(decrease_records.total_cost_loss) as total_loss'))
            ->groupBy('suppliers.name')
            ->orderByDesc('total_loss')
            ->first();

        // 6. Producto con Mayor Pérdida
        $topProduct = DB::table('decrease_records')
            ->join('products', 'decrease_records.product_id', '=', 'products.id')
            ->whereBetween('decrease_records.created_at', [$startOfMonth, $endOfMonth])
            ->select('products.name', DB::raw('SUM(decrease_records.total_cost_loss) as total_loss'))
            ->groupBy('products.name')
            ->orderByDesc('total_loss')
            ->first();

        // 7. Costo Promedio por Unidad
        $avgCostPerUnit = $totalQuantity > 0 ? $totalCostLoss / $totalQuantity : 0;

        // 8. % Merma vs. Margen Bruto (Requires Sales Data)
        $totalSalesMargin = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->whereBetween('sales.created_at', [$startOfMonth, $endOfMonth])
            ->sum(DB::raw('sale_items.quantity * (sale_items.unit_price - products.cost)'));

        $lossVsMarginPct = $totalSalesMargin > 0
            ? ($marginLoss / $totalSalesMargin) * 100
            : 0;

        // 9, 10, 11. Loss by Type
        $lossByType = (clone $recordsQuery)
            ->select('type', DB::raw('SUM(total_cost_loss) as total'))
            ->groupBy('type')
            ->pluck('total', 'type')
            ->toArray();

        // 12. Total Registros
        $totalRecords = (clone $recordsQuery)->count();

        // Get Records for Table
        $records = (clone $recordsQuery)
            ->orderBy('created_at', 'desc')
            ->get();

        // Month Selection
        $monthOptions = [];
        for ($i = 0; $i < 12; $i++) {
            $date = Carbon::now()->subMonths($i);
            $monthOptions[] = [
                'value' => $date->format('Y-m'),
                'label' => ucfirst($date->translatedFormat('F Y')),
            ];
        }

        return Inertia::render('Tenant/Decrease/Index', [
            'kpis' => [
                'total_cost_loss' => $totalCostLoss,
                'total_margin_loss' => $marginLoss,
                'total_potential_loss' => $totalPotentialLoss,
                'top_supplier' => $topSupplier,
                'total_quantity' => $totalQuantity,
                'top_product' => $topProduct,
                'avg_cost_per_unit' => $avgCostPerUnit,
                'loss_vs_margin_pct' => $lossVsMarginPct,
                'sales_margin_base' => $totalSalesMargin,
                'loss_by_expiration' => $lossByType['vencimiento'] ?? 0,
                'loss_by_damage' => $lossByType['daño'] ?? 0,
                'loss_by_return' => $lossByType['devolucion'] ?? 0,
                'total_records' => $totalRecords,
            ],
            'records' => $records,
            'selectedMonth' => $selectedMonth,
            'monthOptions' => $monthOptions,
            'lossByType' => $lossByType,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'type' => 'required|in:vencimiento,daño,devolucion',
            'notes' => 'nullable|string|max:255',
        ]);

        return DB::transaction(function () use ($request) {
            $product = Product::lockForUpdate()->find($request->product_id);

            if ($product->stock < $request->quantity) {
                 return back()->withErrors(['quantity' => "Stock insuficiente. Disponible: {$product->stock}"]);
            }

            $costPerUnit = $product->cost;
            $totalLoss = $request->quantity * $costPerUnit;

            // Decrement Stock
            $product->decrement('stock', $request->quantity);

            // Record Log
            DecreaseRecord::create([
                'product_id' => $product->id,
                'supplier_id' => $product->supplier_id,
                'user_id' => Auth::id(),
                'quantity' => $request->quantity,
                'type' => $request->type,
                'cost_per_unit' => $costPerUnit,
                'total_cost_loss' => $totalLoss,
                'reason_notes' => $request->notes,
            ]);

            return back()->with('success', 'Merma registrada correctamente.');
        });
    }

    // Reingreso (Undo/Restock)
    public function destroy(DecreaseRecord $decrease)
    {
        return DB::transaction(function () use ($decrease) {
            // Revert Stock
            $product = Product::lockForUpdate()->find($decrease->product_id);
            if ($product) {
                $product->increment('stock', $decrease->quantity);
            }

            $decrease->delete();

            return back()->with('success', 'Registro eliminado y stock repuesto al inventario.');
        });
    }
}
