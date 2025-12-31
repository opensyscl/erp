<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class POSController extends Controller
{
    public function __construct(
        protected CurrentTenant $currentTenant
    ) {}

    /**
     * Display the POS interface.
     */
    public function index(): Response
    {
        // Get all active products with their categories
        $products = Product::with('category')
            ->active()
            ->orderBy('name')
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'barcode' => $p->sku, // Using SKU as barcode for now
                'price' => (float) $p->price,
                'cost' => (float) $p->cost,
                'stock' => (float) $p->stock,
                'image' => $p->image,
                'category_id' => $p->category_id,
                'category_name' => $p->category?->name,
            ]);

        // Get all categories
        $categories = Category::orderBy('name')->get(['id', 'name']);

        // Get next receipt number
        $lastSale = Sale::orderByDesc('receipt_number')->first();
        $nextReceiptNumber = ($lastSale?->receipt_number ?? 0) + 1;

        // Get tenant settings for ticket
        $tenant = $this->currentTenant->get();

        return Inertia::render('Tenant/POS/Index', [
            'products' => $products,
            'categories' => $categories,
            'nextReceiptNumber' => $nextReceiptNumber,
            'storeSettings' => [
                'company_name' => $tenant->getSetting('company_name', ''),
                'company_rut' => $tenant->getSetting('company_rut', ''),
                'company_address' => $tenant->getSetting('company_address', ''),
                'company_phone' => $tenant->getSetting('company_phone', ''),
                'company_email' => $tenant->getSetting('company_email', ''),
                'logo' => $tenant->getSetting('logo', ''),
            ],
        ]);
    }

    /**
     * Process a sale.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,debit,credit,transfer',
            'paid_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        try {
            $sale = DB::transaction(function () use ($validated, $request) {
                // Calculate totals
                $subtotal = 0;
                $costTotal = 0;

                foreach ($validated['items'] as $item) {
                    $subtotal += $item['quantity'] * $item['unit_price'];
                }

                // Get next receipt number
                $lastSale = Sale::lockForUpdate()->orderByDesc('receipt_number')->first();
                $receiptNumber = ($lastSale?->receipt_number ?? 0) + 1;

                // Calculate tax (assuming prices include IVA)
                $total = $subtotal;
                $netSubtotal = round($subtotal / 1.19, 2);
                $tax = $total - $netSubtotal;

                // Payment
                $paidAmount = $validated['paid_amount'] ?? $total;
                $change = max(0, $paidAmount - $total);

                // Create sale
                $sale = Sale::create([
                    'tenant_id' => $this->currentTenant->id(),
                    'user_id' => $request->user()->id,
                    'subtotal' => $netSubtotal,
                    'tax' => $tax,
                    'discount' => 0,
                    'total' => $total,
                    'paid' => $paidAmount,
                    'change' => $change,
                    'receipt_number' => $receiptNumber,
                    'payment_method' => $validated['payment_method'],
                    'status' => 'completed',
                    'notes' => $validated['notes'] ?? null,
                ]);

                // Create items and update stock
                foreach ($validated['items'] as $itemData) {
                    $product = Product::find($itemData['product_id']);

                    // Create sale item
                    SaleItem::create([
                        'sale_id' => $sale->id,
                        'product_id' => $product->id,
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                        'unit_cost' => $product->cost,
                        'discount' => 0,
                        'subtotal' => $itemData['quantity'] * $itemData['unit_price'],
                    ]);

                    // Update stock
                    $product->stock -= $itemData['quantity'];
                    $product->save();

                    // Track cost
                    $costTotal += $itemData['quantity'] * (float) $product->cost;
                }

                // Update sale with cost of goods sold
                $sale->update(['cost_of_goods_sold' => $costTotal]);

                return $sale;
            });

            return back()->with('flash', [
                'success' => true,
                'receipt_number' => $sale->receipt_number,
                'total' => $sale->total,
                'change' => $sale->change,
            ]);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al procesar la venta: ' . $e->getMessage()]);
        }
    }

    /**
     * Get sale details for refund.
     */
    public function getSale(int $saleId): JsonResponse
    {
        $sale = Sale::with(['items.product'])
            ->find($saleId);

        if (!$sale) {
            return response()->json(['error' => 'Venta no encontrada.'], 404);
        }

        return response()->json([
            'id' => $sale->id,
            'receipt_number' => $sale->receipt_number,
            'total' => $sale->total,
            'status' => $sale->status,
            'created_at' => $sale->created_at->format('d/m/Y H:i'),
            'items' => $sale->items->map(fn($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product->name,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
            ]),
        ]);
    }

    /**
     * Get sale details by receipt number for refund lookup.
     */
    public function getSaleByReceipt(int $receiptNumber): JsonResponse
    {
        $sale = Sale::with(['items.product'])
            ->where('receipt_number', $receiptNumber)
            ->first();

        if (!$sale) {
            return response()->json(['error' => 'Ticket no encontrado.'], 404);
        }

        return response()->json([
            'id' => $sale->id,
            'receipt_number' => (string) $sale->receipt_number,
            'total' => (float) $sale->total,
            'payment_method' => $sale->payment_method,
            'change' => (float) $sale->change,
            'status' => $sale->status,
            'time' => $sale->created_at->format('H:i'),
            'items' => $sale->items->map(fn($item) => [
                'product_id' => $item->product_id,
                'name' => $item->product->name ?? 'Producto Eliminado',
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'subtotal' => (float) $item->subtotal,
                'image' => $item->product->image ?? null,
            ]),
            'returned_quantities' => [], // TODO: Track returned quantities in DB
            'is_fully_returned' => $sale->status === 'complete_refund',
        ]);
    }

    /**
     * Process a refund.
     */
    public function refund(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sale_id' => 'required|exists:sales,id',
            'items' => 'required|array|min:1',
            'items.*.sale_item_id' => 'required|exists:sale_items,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
        ]);

        try {
            $result = DB::transaction(function () use ($validated) {
                $sale = Sale::findOrFail($validated['sale_id']);
                $totalRefunded = 0;

                foreach ($validated['items'] as $refundItem) {
                    $saleItem = SaleItem::where('id', $refundItem['sale_item_id'])
                        ->where('sale_id', $sale->id)
                        ->first();

                    if (!$saleItem) continue;

                    $qtyToRefund = min($refundItem['quantity'], (float) $saleItem->quantity);
                    if ($qtyToRefund <= 0) continue;

                    // Calculate refund amount
                    $refundAmount = $qtyToRefund * (float) $saleItem->unit_price;
                    $totalRefunded += $refundAmount;

                    // Restore stock
                    $product = Product::find($saleItem->product_id);
                    if ($product) {
                        $product->stock += $qtyToRefund;
                        $product->save();
                    }

                    // Update or delete sale item
                    $remainingQty = (float) $saleItem->quantity - $qtyToRefund;
                    if ($remainingQty <= 0.001) {
                        $saleItem->delete();
                    } else {
                        $saleItem->update([
                            'quantity' => $remainingQty,
                            'subtotal' => $remainingQty * (float) $saleItem->unit_price,
                        ]);
                    }
                }

                // Update sale total
                $newTotal = max(0, (float) $sale->total - $totalRefunded);
                $remainingItems = SaleItem::where('sale_id', $sale->id)->count();

                $sale->update([
                    'total' => $newTotal,
                    'status' => $remainingItems === 0 ? 'complete_refund' : 'partial_refund',
                ]);

                return [
                    'total_refunded' => $totalRefunded,
                    'new_total' => $newTotal,
                    'status' => $sale->status,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Devolución procesada correctamente.',
                'total_refunded' => $result['total_refunded'],
                'new_total' => $result['new_total'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al procesar la devolución: ' . $e->getMessage(),
            ], 500);
        }
    }
}
