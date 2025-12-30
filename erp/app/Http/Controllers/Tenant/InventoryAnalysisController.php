<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Supplier;
use App\Services\CurrentTenant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;

class InventoryAnalysisController extends Controller
{
    public function __construct(
        protected CurrentTenant $currentTenant
    ) {}

    /**
     * Display inventory analysis dashboard.
     */
    public function index(Request $request): Response
    {
        $supplierId = $request->input('supplier');

        // Stock Analysis
        $stockAnalysis = [
            'total_products' => Product::active()->count(),
            'total_value' => Product::active()->selectRaw('SUM(price * stock) as value')->value('value') ?? 0,
            'total_cost' => Product::active()->selectRaw('SUM(cost * stock) as cost')->value('cost') ?? 0,
            'low_stock' => Product::active()->lowStock()->count(),
            'low_stock_threshold_40' => Product::active()->where('stock', '<=', 40)->where('stock', '>', 0)->count(),
            'critical_stock' => Product::active()->where('stock', '<', 10)->where('stock', '>', 0)->count(),
            'out_of_stock' => Product::active()->outOfStock()->count(),
            'negative_stock' => Product::active()->where('stock', '<', 0)->count(),
            'without_image' => Product::active()->withoutImage()->count(),
            'without_supplier' => Product::active()->withoutSupplier()->count(),
            'archived' => Product::archived()->count(),
        ];

        // Get all suppliers for the filter dropdown (those with products)
        $suppliers = Supplier::whereHas('products')
            ->orWhere('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        // Products for the analysis table (low stock + critical)
        $criticalProductsQuery = Product::with(['supplier'])
            ->active()
            ->where('stock', '<=', 40);

        // Apply supplier filter if provided
        if ($supplierId) {
            $criticalProductsQuery->where('supplier_id', $supplierId);
        }

        $criticalProducts = $criticalProductsQuery
            ->orderBy('stock')
            ->get()
            ->map(function ($product) {
                // TODO: These would come from actual sales data when implemented
                // For now, we'll calculate placeholder values or return null
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'price' => $product->price,
                    'cost' => $product->cost,
                    'stock' => $product->stock,
                    'min_stock' => $product->min_stock,
                    'image' => $product->image,
                    'supplier' => $product->supplier ? [
                        'id' => $product->supplier->id,
                        'name' => $product->supplier->name,
                    ] : null,
                    // Placeholder sales metrics - would be calculated from sales table
                    'sales_since_last_purchase' => null,
                    'daily_sales_avg' => null,
                    'days_remaining' => null,
                    'last_purchase_date' => null,
                ];
            });

        return Inertia::render('Tenant/Inventory/Analysis', [
            'stockAnalysis' => $stockAnalysis,
            'criticalProducts' => $criticalProducts,
            'suppliers' => $suppliers,
        ]);
    }
}
