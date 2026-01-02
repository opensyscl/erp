<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Supplier;
use App\Models\Product;
use App\Services\CurrentTenant;

class OrdersController extends Controller
{
    private const IVA_RATE = 0.19;

    private CurrentTenant $currentTenant;

    public function __construct(CurrentTenant $currentTenant)
    {
        $this->currentTenant = $currentTenant;
    }

    public function index(Request $request): Response
    {
        $tenant = $this->currentTenant->get();
        $supplierId = $request->query('supplier');

        $suppliers = Supplier::where('tenant_id', $tenant->id)
            ->select('id', 'name', 'next_order_correlative')
            ->orderBy('name')
            ->get();

        $selectedSupplier = null;
        $products = [];
        $orders = [];

        if ($supplierId) {
            $selectedSupplier = Supplier::where('tenant_id', $tenant->id)
                ->where('id', $supplierId)
                ->first();

            if ($selectedSupplier) {
                $products = Product::where('tenant_id', $tenant->id)
                    ->where('supplier_id', $supplierId)
                    ->select('id', 'barcode', 'name', 'stock', 'cost', 'price', 'image')
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

                $orders = DB::table('purchase_orders')
                    ->leftJoin('users', 'purchase_orders.created_by', '=', 'users.id')
                    ->where('purchase_orders.tenant_id', $tenant->id)
                    ->where('purchase_orders.supplier_id', $supplierId)
                    ->select(
                        'purchase_orders.id',
                        'purchase_orders.order_number',
                        'purchase_orders.date',
                        'purchase_orders.total_amount',
                        'users.name as created_by_name'
                    )
                    ->orderByDesc('purchase_orders.created_at')
                    ->get();
            }
        }

        return Inertia::render('Tenant/Orders/Index', [
            'suppliers' => $suppliers,
            'selectedSupplier' => $selectedSupplier,
            'products' => $products,
            'orders' => $orders,
            'ivaRate' => self::IVA_RATE,
        ]);
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.cost_net' => 'required|numeric|min:0',
        ]);

        $tenant = $this->currentTenant->get();
        $supplierId = $request->input('supplier_id');

        DB::beginTransaction();

        try {
            $supplier = Supplier::where('tenant_id', $tenant->id)
                ->where('id', $supplierId)
                ->lockForUpdate()
                ->first();

            if (!$supplier) {
                throw new \Exception('Proveedor no encontrado');
            }

            // Get correlative
            $correlative = $supplier->next_order_correlative ?? 101;
            $orderNumber = 'OC-' . $supplierId . '-' . str_pad($correlative, 4, '0', STR_PAD_LEFT);

            // Calculate totals
            $subtotalNet = 0;
            foreach ($request->input('items') as $item) {
                $subtotalNet += $item['cost_net'] * $item['quantity'];
            }
            $ivaAmount = round($subtotalNet * self::IVA_RATE);
            $totalAmount = $subtotalNet + $ivaAmount;

            // Create order
            $orderId = DB::table('purchase_orders')->insertGetId([
                'tenant_id' => $tenant->id,
                'order_number' => $orderNumber,
                'order_correlative' => $correlative,
                'supplier_id' => $supplierId,
                'date' => $request->input('date'),
                'subtotal_net' => $subtotalNet,
                'iva_amount' => $ivaAmount,
                'total_amount' => $totalAmount,
                'created_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create items and handle new products
            foreach ($request->input('items') as $item) {
                $productId = $item['product_id'];
                $costNet = (float) $item['cost_net'];
                $costGross = round($costNet * (1 + self::IVA_RATE));
                $lineTotal = $costGross * $item['quantity'];

                // Create new product if product_id is negative
                if ($productId < 0) {
                    $newProductId = DB::table('products')->insertGetId([
                        'tenant_id' => $tenant->id,
                        'supplier_id' => $supplierId,
                        'barcode' => $item['code'] ?? '',
                        'name' => $item['name'],
                        'stock' => 0,
                        'cost' => $costNet,
                        'price' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $productId = $newProductId;
                } else {
                    // Update cost of existing product
                    DB::table('products')
                        ->where('id', $productId)
                        ->where('tenant_id', $tenant->id)
                        ->update([
                            'cost' => $costNet,
                            'updated_at' => now(),
                        ]);
                }

                DB::table('purchase_order_items')->insert([
                    'order_id' => $orderId,
                    'product_id' => $productId,
                    'product_code' => $item['code'] ?? '',
                    'product_name' => $item['name'],
                    'quantity' => $item['quantity'],
                    'cost_net' => $costNet,
                    'cost_gross' => $costGross,
                    'line_total' => $lineTotal,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Update correlative
            $supplier->next_order_correlative = $correlative + 1;
            $supplier->save();

            DB::commit();

            return redirect()->back()
                ->with('success', true)
                ->with('message', "Orden de Compra {$orderNumber} generada con éxito");

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Error al generar orden: ' . $e->getMessage());
        }
    }

    public function show(int $id): Response
    {
        $tenant = $this->currentTenant->get();

        $order = DB::table('purchase_orders')
            ->leftJoin('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.id')
            ->leftJoin('users', 'purchase_orders.created_by', '=', 'users.id')
            ->where('purchase_orders.tenant_id', $tenant->id)
            ->where('purchase_orders.id', $id)
            ->select(
                'purchase_orders.*',
                'suppliers.name as supplier_name',
                'users.name as created_by_name'
            )
            ->first();

        if (!$order) {
            abort(404, 'Orden no encontrada');
        }

        $items = DB::table('purchase_order_items')
            ->where('order_id', $id)
            ->get();

        return Inertia::render('Tenant/Orders/Show', [
            'order' => $order,
            'items' => $items,
        ]);
    }
}
