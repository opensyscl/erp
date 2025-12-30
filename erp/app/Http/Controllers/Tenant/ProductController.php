<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\CurrentTenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(
        protected CurrentTenant $currentTenant
    ) {}

    /**
     * Display inventory dashboard with products grid.
     */
    public function index(Request $request): Response
    {
        $query = Product::with(['category', 'supplier'])
            ->active();

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        // Filter by category
        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }

        // Filter by supplier
        if ($request->filled('supplier')) {
            $query->where('supplier_id', $request->supplier);
        }

        // Filter by stock status
        if ($request->filled('stock_status')) {
            match ($request->stock_status) {
                'low' => $query->lowStock(),
                'out' => $query->outOfStock(),
                'negative' => $query->where('stock', '<', 0),
                'without_image' => $query->withoutImage(),
                'without_supplier' => $query->withoutSupplier(),
                'archived' => $query->archived(),
                default => null,
            };
        }

        $allowedSortFields = ['name', 'price', 'stock', 'created_at', 'updated_at', 'cost', 'sku'];
        $sortField = $request->input('sort', 'updated_at');
        $sortDir = strtolower($request->input('dir', 'desc'));


        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = 'updated_at';
        }
        if (!in_array($sortDir, ['asc', 'desc'])) {
            $sortDir = 'desc';
        }

        $query->orderBy($sortField, $sortDir);

        $products = $query->paginate(5)->withQueryString();

        // Stats
        $stats = [
            'total' => Product::active()->count(),
            'lowStock' => Product::active()->lowStock()->count(),
            'outOfStock' => Product::active()->outOfStock()->count(),
            'withoutImage' => Product::active()->withoutImage()->count(),
            'withoutSupplier' => Product::active()->withoutSupplier()->count(),
            'negativeStock' => Product::active()->where('stock', '<', 0)->count(),
            'archived' => Product::archived()->count(),
        ];

        $categories = Category::where('is_active', true)
            ->withCount(['products' => fn($q) => $q->active()])
            ->orderBy('name')
            ->get(['id', 'name']);
        $suppliers = Supplier::where('is_active', true)
            ->withCount(['products' => fn($q) => $q->active()])
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Tenant/Inventory/Index', [
            'products' => $products,
            'stats' => $stats,
            'categories' => $categories,
            'suppliers' => $suppliers,
            'filters' => $request->only(['search', 'category', 'supplier', 'stock_status', 'sort', 'dir']),
        ]);
    }

    /**
     * Show the form for creating a new product.
     */
    public function create(): Response
    {
        $categories = Category::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Tenant/Inventory/Products/Create', [
            'categories' => $categories,
            'suppliers' => $suppliers,
        ]);
    }

    /**
     * Store a newly created product.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'max:2048'],
        ]);

        // Handle image upload
        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        // Generate slug
        $validated['slug'] = Str::slug($validated['name']);

        // Default values for non-nullable fields
        $validated['cost'] ??= 0;
        $validated['stock'] ??= 0;
        $validated['min_stock'] ??= 0;

        Product::create($validated);

        return redirect()->route('tenant.inventory.index', ['tenant' => $this->currentTenant->get()->slug])
            ->with('success', 'Producto creado exitosamente.');
    }

    /**
     * Show the form for editing the specified product.
     */
    public function edit(string $productId): Response
    {
        $product = Product::findOrFail($productId);
        $categories = Category::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Tenant/Inventory/Products/Edit', [
            'product' => $product,
            'categories' => $categories,
            'suppliers' => $suppliers,
        ]);
    }

    /**
     * Update the specified product.
     */
    public function update(Request $request, string $productId)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'image' => ['nullable', 'image', 'max:2048'],
        ]);

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        // Default values for non-nullable fields
        $validated['cost'] ??= 0;
        $validated['stock'] ??= 0;
        $validated['min_stock'] ??= 0;

        $product = Product::findOrFail($productId);
        $product->update($validated);

        return redirect()->route('tenant.inventory.index', ['tenant' => $this->currentTenant->get()->slug])
            ->with('success', 'Producto actualizado.');
    }

    /**
     * Archive the specified product (soft delete).
     */
    public function archive(string $productId)
    {
        $logPo =  [
            'productId' => $productId,
            'request_url' => request()->url(),
            'request_method' => request()->method(),
        ];

        $product = Product::findOrFail($productId);

        $product->update(['is_archived' => true]);

        return back()->with('success', 'Producto archivado.');
    }

    /**
     * Permanently delete the specified product.
     */
    public function destroy(string $productId)
    {
        $product = Product::findOrFail($productId);

        // Delete image if exists
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();

        return back()->with('success', 'Producto eliminado permanentemente.');
    }

    /**
     * Update product stock quickly.
     */
    public function updateStock(Request $request, string $productId)
    {
        $validated = $request->validate([
            'stock' => ['required', 'integer'],
        ]);

        $product = Product::findOrFail($productId);
        $product->update($validated);

        return back()->with('success', 'Stock actualizado.');
    }

    /**
     * Restore an archived product.
     */
    public function restore(string $productId)
    {
        $product = Product::archived()->findOrFail($productId);
        $product->update(['is_archived' => false]);

        return back()->with('success', 'Producto restaurado.');
    }

    /**
     * Duplicate a product.
     */
    public function duplicate(string $productId)
    {
        $product = Product::findOrFail($productId);

        $newProduct = $product->replicate();
        $newProduct->name = $product->name . ' (Copia)';
        $newProduct->slug = Str::slug($newProduct->name) . '-' . time();
        $newProduct->sku = $product->sku ? $product->sku . '-COPY' : null;
        $newProduct->save();

        return back()->with('success', 'Producto duplicado exitosamente.');
    }
}
