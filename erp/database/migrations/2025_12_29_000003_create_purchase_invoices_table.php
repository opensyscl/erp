<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('purchase_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('invoice_number', 100); // Número de factura del proveedor
            $table->date('invoice_date'); // Fecha de la factura
            $table->date('received_date')->nullable(); // Fecha de recepción de mercadería

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0); // IVA
            $table->decimal('total_amount', 12, 2)->default(0);

            $table->boolean('is_paid')->default(false);
            $table->date('payment_date')->nullable();
            $table->date('payment_due_date')->nullable(); // Fecha de vencimiento
            $table->string('payment_method', 50)->nullable();

            $table->enum('status', [
                'draft',
                'received',
                'paid',
                'partial_paid',
                'cancelled'
            ])->default('received');

            $table->text('notes')->nullable();
            $table->timestamps();

            // Índices
            $table->index(['tenant_id', 'supplier_id']);
            $table->index(['tenant_id', 'invoice_date']);
            $table->index(['tenant_id', 'status']);
            $table->unique(['tenant_id', 'supplier_id', 'invoice_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_invoices');
    }
};
