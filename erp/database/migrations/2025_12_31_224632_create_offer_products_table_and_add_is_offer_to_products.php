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
        // Add is_offer column to products
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_offer')->default(false)->after('is_archived');
        });

        // Create offer_products table for pack items
        Schema::create('offer_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('offer_id'); // FK to products.id where is_offer=1
            $table->unsignedBigInteger('product_id'); // FK to products.id
            $table->integer('quantity')->default(1);
            $table->decimal('original_price', 10, 2); // Original price per unit
            $table->decimal('offer_price', 10, 2); // Discounted price per unit
            $table->timestamps();

            $table->foreign('offer_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->index(['offer_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offer_products');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_offer');
        });
    }
};
