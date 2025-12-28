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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();

            // Basic info
            $table->string('name');
            $table->string('slug');
            $table->string('sku')->nullable();
            $table->string('barcode')->nullable();
            $table->text('description')->nullable();

            // Pricing
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('cost', 12, 2)->default(0); // Costo bruto
            $table->decimal('compare_price', 12, 2)->nullable(); // Precio anterior para mostrar descuento

            // Stock
            $table->integer('stock')->default(0);
            $table->integer('min_stock')->default(0); // Alerta de stock bajo
            $table->boolean('track_stock')->default(true);

            // Media
            $table->string('image')->nullable();
            $table->json('images')->nullable(); // Multiple images

            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_archived')->default(false);

            $table->timestamps();

            $table->unique(['tenant_id', 'sku']);
            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'is_active', 'is_archived']);
            $table->index(['tenant_id', 'category_id']);
            $table->index(['tenant_id', 'supplier_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
