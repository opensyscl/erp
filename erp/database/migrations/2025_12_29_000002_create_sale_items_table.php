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
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();

            $table->decimal('quantity', 12, 3)->default(1); // Soporta productos a granel
            $table->decimal('unit_price', 12, 2); // Precio unitario al momento de venta
            $table->decimal('unit_cost', 12, 2)->nullable(); // Costo unitario al momento de venta
            $table->decimal('discount', 12, 2)->default(0); // Descuento aplicado al ítem
            $table->decimal('subtotal', 12, 2); // quantity * unit_price - discount

            $table->timestamps();

            // Índices
            $table->index(['sale_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
