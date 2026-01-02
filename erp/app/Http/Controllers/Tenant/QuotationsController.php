<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class QuotationsController extends Controller
{
    private const IVA_RATE = 0.19;

    public function __construct(
        protected CurrentTenant $currentTenant
    ) {}

    /**
     * Display quotations page with supplier selection.
     */
    public function index(Request $request): Response
    {
        $tenant = $this->currentTenant->get();
        $supplierId = $request->query('supplier');

        // Get all suppliers
        $suppliers = Supplier::where('tenant_id', $tenant->id)
            ->select('id', 'name', 'next_quotation_number')
            ->orderBy('name')
            ->get();

        // Default to first supplier if none selected
        if (!$supplierId && $suppliers->isNotEmpty()) {
            $supplierId = $suppliers->first()->id;
        }

        $selectedSupplier = null;
        $products = [];
        $quotations = [];

        if ($supplierId) {
            $selectedSupplier = Supplier::where('tenant_id', $tenant->id)
                ->where('id', $supplierId)
                ->first();

            if ($selectedSupplier) {
                // Get products for this supplier
                $products = Product::where('tenant_id', $tenant->id)
                    ->where('supplier_id', $supplierId)
                    ->where('is_archived', false)
                    ->select('id', 'name', 'barcode', 'cost', 'price', 'stock', 'image')
                    ->orderBy('name')
                    ->get()
                    ->map(function ($product) {
                        $cost = (float) ($product->cost ?? 0);
                        $price = (float) ($product->price ?? 0);
                        return [
                            'id' => $product->id,
                            'code' => $product->barcode,
                            'name' => $product->name,
                            'stock' => $product->stock,
                            'cost_net' => round($cost),
                            'cost_gross' => round($cost * (1 + self::IVA_RATE)),
                            'sale_price' => round($price),
                            'image' => $product->image,
                        ];
                    });

                // Get quotations for this supplier
                $quotations = DB::table('quotations')
                    ->leftJoin('users', 'quotations.created_by', '=', 'users.id')
                    ->where('quotations.tenant_id', $tenant->id)
                    ->where('quotations.supplier_id', $supplierId)
                    ->select(
                        'quotations.id',
                        'quotations.quotation_number',
                        'quotations.date',
                        'quotations.total_amount',
                        'users.name as created_by_name'
                    )
                    ->orderByDesc('quotations.created_at')
                    ->limit(50)
                    ->get();
            }
        }

        return Inertia::render('Tenant/Quotations/Index', [
            'suppliers' => $suppliers,
            'selectedSupplier' => $selectedSupplier,
            'products' => $products,
            'quotations' => $quotations,
            'ivaRate' => self::IVA_RATE,
        ]);
    }

    /**
     * Store a new quotation.
     */
    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|integer',
            'items.*.code' => 'required|string',
            'items.*.name' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.cost_net' => 'required|numeric|min:0',
            'items.*.cost_gross' => 'required|numeric|min:0',
        ]);

        $tenant = $this->currentTenant->get();
        $supplierId = $request->input('supplier_id');

        try {
            DB::beginTransaction();

            // Get supplier and lock for update
            $supplier = Supplier::where('tenant_id', $tenant->id)
                ->where('id', $supplierId)
                ->lockForUpdate()
                ->first();

            if (!$supplier) {
                throw new \Exception('Proveedor no encontrado');
            }

            $correlative = $supplier->next_quotation_number ?? 1;
            $quotationNumber = sprintf('C-%02d-%03d', $supplierId, $correlative);

            // Calculate totals
            $subtotalNet = 0;
            foreach ($request->input('items') as $item) {
                $subtotalNet += $item['cost_net'] * $item['quantity'];
            }
            $ivaAmount = round($subtotalNet * self::IVA_RATE);
            $totalAmount = $subtotalNet + $ivaAmount;

            // Create quotation
            $quotationId = DB::table('quotations')->insertGetId([
                'tenant_id' => $tenant->id,
                'quotation_number' => $quotationNumber,
                'supplier_id' => $supplierId,
                'date' => $request->input('date'),
                'subtotal_net' => $subtotalNet,
                'iva_amount' => $ivaAmount,
                'total_amount' => $totalAmount,
                'created_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create items
            foreach ($request->input('items') as $item) {
                $lineTotal = $item['cost_gross'] * $item['quantity'];
                DB::table('quotation_items')->insert([
                    'quotation_id' => $quotationId,
                    'product_id' => $item['product_id'] > 0 ? $item['product_id'] : null,
                    'product_code' => $item['code'],
                    'product_name' => $item['name'],
                    'quantity' => $item['quantity'],
                    'cost_net' => $item['cost_net'],
                    'cost_gross' => $item['cost_gross'],
                    'line_total' => $lineTotal,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Update correlative
            $supplier->next_quotation_number = $correlative + 1;
            $supplier->save();

            DB::commit();

            return redirect()->back()
                ->with('success', true)
                ->with('message', "Cotización {$quotationNumber} generada con éxito");

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Error al generar cotización: ' . $e->getMessage());
        }
    }

    /**
     * View quotation details.
     */
    public function show(int $id): \Inertia\Response
    {
        $tenant = $this->currentTenant->get();

        $quotation = DB::table('quotations')
            ->leftJoin('suppliers', 'quotations.supplier_id', '=', 'suppliers.id')
            ->leftJoin('users', 'quotations.created_by', '=', 'users.id')
            ->where('quotations.tenant_id', $tenant->id)
            ->where('quotations.id', $id)
            ->select(
                'quotations.*',
                'suppliers.name as supplier_name',
                'users.name as created_by_name'
            )
            ->first();

        if (!$quotation) {
            abort(404, 'Cotización no encontrada');
        }

        $items = DB::table('quotation_items')
            ->where('quotation_id', $id)
            ->get();

        return Inertia::render('Tenant/Quotations/Show', [
            'quotation' => $quotation,
            'items' => $items,
        ]);
    }
}
