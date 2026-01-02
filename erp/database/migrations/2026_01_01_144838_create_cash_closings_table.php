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
        Schema::create('cash_closings', function (Blueprint $table) {
            $table->id();
            $table->date('closing_date')->index();

            // Ingresos
            $table->decimal('starting_cash', 10, 0)->default(0);
            $table->decimal('ending_cash', 10, 0)->default(0);
            $table->decimal('pos1_sales', 10, 0)->default(0);
            $table->decimal('pos2_sales', 10, 0)->default(0);
            $table->decimal('total_day_cash', 10, 0)->default(0); // Suma POS1 + POS2

            // Egresos
            $table->decimal('deposit_meli', 10, 0)->default(0);
            $table->decimal('deposit_bchile', 10, 0)->default(0);
            $table->decimal('deposit_bsantander', 10, 0)->default(0);
            $table->decimal('other_outgoings', 10, 0)->default(0);
            $table->decimal('total_outgoings', 10, 0)->default(0);

            // Totales Calculados
            $table->decimal('total_day_income', 10, 0)->default(0); // Flujo neto: (Final - Inicial) + Ventas
            $table->decimal('income_plus_outgoings', 10, 0)->default(0); // Flujo + Egresos

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_closings');
    }
};
