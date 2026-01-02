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
        Schema::create('internal_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('restrict');
            $table->foreignId('user_id')->constrained(); // User who registered the action
            $table->integer('quantity_removed');
            $table->decimal('cost_price_at_time', 15, 2);
            $table->decimal('sale_price_at_time', 15, 2);
            $table->text('notes')->nullable();
            $table->timestamp('removal_date')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('internal_consumptions');
    }
};
