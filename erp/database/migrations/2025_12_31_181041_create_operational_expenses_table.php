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
        Schema::create('operational_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->date('date_paid');
            $table->enum('expense_type', ['Fijo', 'Variable'])->default('Fijo');
            $table->string('description');
            $table->decimal('total_amount', 12, 2)->default(0);
            // Gastos Fijos
            $table->decimal('light', 12, 2)->default(0);
            $table->decimal('water', 12, 2)->default(0);
            $table->decimal('rent', 12, 2)->default(0);
            $table->decimal('alarm', 12, 2)->default(0);
            $table->decimal('internet', 12, 2)->default(0);
            $table->decimal('iva', 12, 2)->default(0);
            // Gastos Variables
            $table->decimal('repairs', 12, 2)->default(0);
            $table->decimal('supplies', 12, 2)->default(0);
            $table->decimal('other', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'date_paid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operational_expenses');
    }
};
