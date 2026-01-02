<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PublicQuotationController extends Controller
{
    private const IVA_RATE = 0.19;

    /**
     * Display a quotation publicly (for printing/sharing).
     */
    public function show(int $id): Response
    {
        $quotation = DB::table('quotations')
            ->leftJoin('suppliers', 'quotations.supplier_id', '=', 'suppliers.id')
            ->leftJoin('users', 'quotations.created_by', '=', 'users.id')
            ->leftJoin('tenants', 'quotations.tenant_id', '=', 'tenants.id')
            ->where('quotations.id', $id)
            ->select(
                'quotations.*',
                'suppliers.name as supplier_name',
                'users.name as created_by_name',
                'tenants.name as tenant_name'
            )
            ->first();

        if (!$quotation) {
            abort(404, 'Cotización no encontrada');
        }

        $items = DB::table('quotation_items')
            ->where('quotation_id', $id)
            ->get();

        return Inertia::render('Public/QuotationView', [
            'quotation' => $quotation,
            'items' => $items,
            'tenantName' => $quotation->tenant_name ?? 'Mi Tienda',
            'tenantLogo' => null,
        ]);
    }
}
