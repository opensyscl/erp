<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\CurrentTenant;
use Inertia\Inertia;
use Inertia\Response;

class LabelsController extends Controller
{
    public function __construct(
        protected CurrentTenant $currentTenant
    ) {}

    /**
     * Display products for label printing.
     */
    public function index(): Response
    {
        $tenant = $this->currentTenant->get();

        // Get products (most recent first, limit 1000)
        $products = Product::where('tenant_id', $tenant->id)
            ->where('is_archived', false)
            ->select('id', 'name', 'barcode', 'price', 'stock', 'image', 'category_id', 'supplier_id')
            ->orderByDesc('id')
            ->limit(1000)
            ->get();

        // KPIs
        $totalProducts = $products->count();
        $noBarcode = $products->filter(fn($p) => empty($p->barcode) || $p->barcode === '0')->count();
        $withImage = $products->filter(fn($p) => !empty($p->image))->count();
        $withImagePercent = $totalProducts > 0 ? round(($withImage / $totalProducts) * 100) : 0;
        $outOfStock = $products->filter(fn($p) => $p->stock <= 0)->count();
        $avgPrice = $totalProducts > 0 ? $products->avg('price') : 0;

        // Categories and Suppliers for filters
        $categories = Category::where('tenant_id', $tenant->id)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $suppliers = Supplier::where('tenant_id', $tenant->id)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Tenant/Labels/Index', [
            'products' => $products,
            'kpis' => [
                'total_products' => $totalProducts,
                'no_barcode' => $noBarcode,
                'with_image_percent' => $withImagePercent,
                'out_of_stock' => $outOfStock,
                'avg_price' => round($avgPrice),
            ],
            'categories' => $categories,
            'suppliers' => $suppliers,
        ]);
    }
}
