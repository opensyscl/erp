<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PublicOrderController extends Controller
{
    /**
     * Display an order publicly (for printing/sharing).
     */
    public function show(int $id): Response
    {
        $order = DB::table('purchase_orders')
            ->leftJoin('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.id')
            ->leftJoin('users', 'purchase_orders.created_by', '=', 'users.id')
            ->leftJoin('tenants', 'purchase_orders.tenant_id', '=', 'tenants.id')
            ->where('purchase_orders.id', $id)
            ->select(
                'purchase_orders.*',
                'suppliers.name as supplier_name',
                'users.name as created_by_name',
                'tenants.name as tenant_name'
            )
            ->first();

        if (!$order) {
            abort(404, 'Orden no encontrada');
        }

        $items = DB::table('purchase_order_items')
            ->where('order_id', $id)
            ->get();

        return Inertia::render('Public/OrderView', [
            'order' => $order,
            'items' => $items,
            'tenantName' => $order->tenant_name ?? 'Mi Tienda',
            'tenantLogo' => null,
        ]);
    }
}
