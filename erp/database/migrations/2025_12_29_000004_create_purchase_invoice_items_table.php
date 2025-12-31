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
        Schema::create('purchase_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();

            $table->decimal('quantity', 12, 3); // Cantidad recibida

            $table->decimal('previous_cost', 12, 2)->nullable(); // Costo anterior del producto
            $table->decimal('new_cost', 12, 2); // Nuevo costo de esta compra

            $table->decimal('margin_percentage', 5, 2)->nullable(); // Margen de ganancia %
            $table->decimal('calculated_sale_price', 12, 2)->nullable(); // Precio de venta calculado

            $table->decimal('subtotal', 12, 2); // quantity * new_cost

            $table->boolean('update_product_cost')->default(true); // Si se debe actualizar el costo del producto
            $table->boolean('update_product_price')->default(false); // Si se debe actualizar el precio del producto

            $table->timestamps();

            // Índices para análisis de inventario
            $table->index(['product_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_invoice_items');
    }
};
