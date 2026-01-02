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
        // Quotations table
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('quotation_number')->unique();
            $table->foreignId('supplier_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->decimal('subtotal_net', 12, 2)->default(0);
            $table->decimal('iva_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'supplier_id']);
        });

        // Quotation items table
        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('set null');
            $table->string('product_code')->nullable();
            $table->string('product_name');
            $table->integer('quantity')->default(1);
            $table->decimal('cost_net', 10, 2)->default(0);
            $table->decimal('cost_gross', 10, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->timestamps();
        });

        // Add next_quotation_number to suppliers if not exists
        if (!Schema::hasColumn('suppliers', 'next_quotation_number')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->unsignedInteger('next_quotation_number')->default(1)->after('phone');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');

        if (Schema::hasColumn('suppliers', 'next_quotation_number')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->dropColumn('next_quotation_number');
            });
        }
    }
};
