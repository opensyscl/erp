<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use App\Models\Category;
use App\Models\Supplier;

class MassProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenantId = 1;
        $count = 1000;

        $categories = Category::pluck('id')->toArray();
        $suppliers = Supplier::pluck('id')->toArray();

        if (empty($categories)) {
            $categories[] = DB::table('categories')->insertGetId([
                'tenant_id' => $tenantId, 'name' => 'General', 'slug' => 'general', 'created_at' => now(), 'updated_at' => now()
            ]);
        }

        if (empty($suppliers)) {
            $suppliers[] = DB::table('suppliers')->insertGetId([
                'tenant_id' => $tenantId, 'name' => 'Proveedor General', 'rut' => '11111111-1', 'created_at' => now(), 'updated_at' => now()
            ]);
        }

        $this->command->info("Generating $count products...");

        $products = [];
        $now = now();

        for ($i = 0; $i < $count; $i++) {
            $price = rand(1000, 50000);
            $cost = $price * 0.6; // 40% margin

            $name = 'Producto Demo ' . ($i + 1);
            $barcode = 'DEMO-' . str_pad($i + 1, 6, '0', STR_PAD_LEFT);

            $products[] = [
                'tenant_id' => $tenantId,
                'name' => $name,
                'slug' => \Illuminate\Support\Str::slug($name) . '-' . uniqid(),
                'sku' => $barcode,
                'barcode' => $barcode,
                'description' => 'Descripción autogenerada para producto demo ' . ($i + 1),
                'price' => $price,
                'cost' => $cost,
                'compare_price' => null,
                'stock' => rand(0, 500),
                'min_stock' => 5,
                'track_stock' => 1,
                'category_id' => $categories[array_rand($categories)],
                'supplier_id' => $suppliers[array_rand($suppliers)],
                'image' => null,
                'images' => null,
                'is_active' => 1,
                'is_offer' => 0,
                'is_archived' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // Batch insert every 500 records
            if (count($products) >= 500) {
                DB::table('products')->insert($products);
                $products = [];
                $this->command->info("Inserted " . ($i + 1) . " products...");
            }
        }

        if (!empty($products)) {
            DB::table('products')->insert($products);
        }

        $this->command->info("Done! $count products created.");
    }
}
