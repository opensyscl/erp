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
        // Add next_order_correlative to suppliers if not exists
        if (!Schema::hasColumn('suppliers', 'next_order_correlative')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->integer('next_order_correlative')->default(101)->after('next_quotation_number');
            });
        }

        // Create purchase_orders table
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('order_number', 50);
            $table->integer('order_correlative');
            $table->unsignedBigInteger('supplier_id');
            $table->date('date');
            $table->decimal('subtotal_net', 12, 2)->default(0);
            $table->decimal('iva_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'supplier_id']);
            $table->index(['tenant_id', 'created_at']);
            $table->unique(['tenant_id', 'order_number']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });

        // Create purchase_order_items table
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('product_code', 100)->nullable();
            $table->string('product_name', 255)->nullable();
            $table->integer('quantity');
            $table->decimal('cost_net', 12, 2);
            $table->decimal('cost_gross', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('purchase_orders')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');

        if (Schema::hasColumn('suppliers', 'next_order_correlative')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->dropColumn('next_order_correlative');
            });
        }
    }
};
