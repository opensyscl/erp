<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OffersController extends Controller
{
    public function __construct(
        protected CurrentTenant $currentTenant
    ) {}

    /**
     * Display offers/packs list with KPIs.
     */
    public function index(): Response
    {
        $tenant = $this->currentTenant->get();

        // KPIs
        $kpis = DB::table('products')
            ->where('tenant_id', $tenant->id)
            ->where('is_offer', true)
            ->where('is_archived', false)
            ->selectRaw('
                COUNT(*) as available_offers,
                COALESCE(SUM(stock), 0) as total_stock,
                COALESCE(SUM(price * stock), 0) as total_retail_value
            ')
            ->first();

        // Offers list with margin calculation
        $offers = DB::table('products')
            ->where('tenant_id', $tenant->id)
            ->where('is_offer', true)
            ->where('is_archived', false)
            ->select('id', 'name', 'barcode', 'price', 'stock', 'cost')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(function ($offer) {
                $marginPercent = $offer->price > 0
                    ? (($offer->price - $offer->cost) / $offer->price) * 100
                    : 0;
                return [
                    'id' => $offer->id,
                    'name' => $offer->name,
                    'barcode' => $offer->barcode,
                    'price' => (float) $offer->price,
                    'cost' => (float) $offer->cost,
                    'stock' => (int) $offer->stock,
                    'margin_percent' => round($marginPercent, 1),
                ];
            });

        return Inertia::render('Tenant/Offers/Index', [
            'kpis' => [
                'available_offers' => (int) $kpis->available_offers,
                'total_stock' => (int) $kpis->total_stock,
                'total_retail_value' => (float) $kpis->total_retail_value,
            ],
            'offers' => $offers,
        ]);
    }

    /**
     * Search products for adding to offer (excludes existing offers).
     */
    public function searchProducts(Request $request): JsonResponse
    {
        $tenant = $this->currentTenant->get();
        $query = $request->get('q', '');

        if (strlen($query) < 2) {
            return response()->json(['products' => []]);
        }

        $products = Product::where('tenant_id', $tenant->id)
            ->where('is_offer', false)
            ->where('is_archived', false)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('barcode', 'like', "%{$query}%")
                  ->orWhere('sku', 'like', "%{$query}%");
            })
            ->select('id', 'name', 'barcode', 'price', 'cost', 'stock', 'image')
            ->limit(10)
            ->get();

        return response()->json(['products' => $products]);
    }

    /**
     * Load offer data for editing.
     */
    public function show(int $id): JsonResponse
    {
        $tenant = $this->currentTenant->get();

        $offer = DB::table('products')
            ->where('id', $id)
            ->where('tenant_id', $tenant->id)
            ->where('is_offer', true)
            ->first();

        if (!$offer) {
            return response()->json(['success' => false, 'message' => 'Oferta no encontrada.'], 404);
        }

        $items = DB::table('offer_products')
            ->join('products', 'products.id', '=', 'offer_products.product_id')
            ->where('offer_products.offer_id', $id)
            ->select(
                'offer_products.product_id as id',
                'products.name',
                'products.stock',
                'products.cost',
                'offer_products.quantity',
                'offer_products.original_price',
                'offer_products.offer_price'
            )
            ->get()
            ->map(function ($item) {
                $discountPercent = $item->original_price > 0
                    ? (($item->original_price - $item->offer_price) / $item->original_price) * 100
                    : 0;
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'stock' => $item->stock,
                    'cost' => (float) $item->cost,
                    'quantity' => $item->quantity,
                    'original_price' => (float) $item->original_price,
                    'offer_price' => (float) $item->offer_price,
                    'discount_percent' => round($discountPercent, 1),
                ];
            });

        return response()->json([
            'success' => true,
            'offer' => [
                'id' => $offer->id,
                'name' => $offer->name,
                'barcode' => $offer->barcode,
                'price' => (float) $offer->price,
                'stock' => (int) $offer->stock,
            ],
            'items' => $items,
        ]);
    }

    /**
     * Store a new offer/pack.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.original_price' => ['required', 'numeric'],
            'items.*.offer_price' => ['required', 'numeric'],
        ]);

        $tenant = $this->currentTenant->get();

        try {
            DB::transaction(function () use ($validated, $tenant) {
                // Calculate total cost
                $totalCost = collect($validated['items'])->sum(function ($item) {
                    $product = Product::find($item['id']);
                    return ($product->cost ?? 0) * $item['quantity'];
                });

                // Create offer product
                $offer = Product::create([
                    'tenant_id' => $tenant->id,
                    'name' => $validated['name'],
                    'barcode' => $validated['barcode'] ?: 'PACK' . time(),
                    'price' => $validated['price'],
                    'cost' => $totalCost,
                    'stock' => $validated['stock'],
                    'is_offer' => true,
                    'is_active' => true,
                    'is_archived' => false,
                ]);

                // Create offer items
                foreach ($validated['items'] as $item) {
                    DB::table('offer_products')->insert([
                        'offer_id' => $offer->id,
                        'product_id' => $item['id'],
                        'quantity' => $item['quantity'],
                        'original_price' => $item['original_price'],
                        'offer_price' => $item['offer_price'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });

            return back()->with('success', 'Oferta creada exitosamente.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error al crear la oferta: ' . $e->getMessage());
        }
    }

    /**
     * Update an existing offer/pack.
     */
    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.original_price' => ['required', 'numeric'],
            'items.*.offer_price' => ['required', 'numeric'],
        ]);

        $tenant = $this->currentTenant->get();

        $offer = Product::where('id', $id)
            ->where('tenant_id', $tenant->id)
            ->where('is_offer', true)
            ->firstOrFail();

        try {
            DB::transaction(function () use ($validated, $offer) {
                // Calculate total cost
                $totalCost = collect($validated['items'])->sum(function ($item) {
                    $product = Product::find($item['id']);
                    return ($product->cost ?? 0) * $item['quantity'];
                });

                // Update offer product
                $offer->update([
                    'name' => $validated['name'],
                    'barcode' => $validated['barcode'] ?: $offer->barcode,
                    'price' => $validated['price'],
                    'cost' => $totalCost,
                    'stock' => $validated['stock'],
                ]);

                // Delete old items and insert new ones
                DB::table('offer_products')->where('offer_id', $offer->id)->delete();

                foreach ($validated['items'] as $item) {
                    DB::table('offer_products')->insert([
                        'offer_id' => $offer->id,
                        'product_id' => $item['id'],
                        'quantity' => $item['quantity'],
                        'original_price' => $item['original_price'],
                        'offer_price' => $item['offer_price'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });

            return back()->with('success', 'Oferta actualizada exitosamente.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error al actualizar la oferta: ' . $e->getMessage());
        }
    }

    /**
     * Delete an offer/pack.
     */
    public function destroy(int $id)
    {
        $tenant = $this->currentTenant->get();

        $offer = Product::where('id', $id)
            ->where('tenant_id', $tenant->id)
            ->where('is_offer', true)
            ->firstOrFail();

        try {
            DB::transaction(function () use ($offer) {
                // Delete offer items first
                DB::table('offer_products')->where('offer_id', $offer->id)->delete();
                // Delete offer product
                $offer->delete();
            });

            return back()->with('success', 'Oferta eliminada exitosamente.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error al eliminar la oferta: ' . $e->getMessage());
        }
    }
}
