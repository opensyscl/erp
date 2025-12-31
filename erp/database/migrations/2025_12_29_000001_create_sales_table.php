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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // Cajero
            $table->foreignId('customer_id')->nullable(); // Para futura implementación de clientes

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0); // IVA
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('paid', 12, 2)->default(0);
            $table->decimal('change', 12, 2)->default(0);

            $table->unsignedInteger('receipt_number')->nullable();
            $table->string('payment_method', 50)->default('cash'); // cash, debit, credit, transfer
            $table->string('voucher')->nullable(); // Referencia de comprobante

            $table->decimal('cost_of_goods_sold', 12, 2)->default(0); // Costo total de los productos vendidos

            $table->enum('status', [
                'pending',
                'completed',
                'partial_refund',
                'complete_refund',
                'cancelled'
            ])->default('completed');

            $table->text('notes')->nullable();
            $table->timestamps();

            // Índices
            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'receipt_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
