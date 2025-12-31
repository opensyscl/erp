<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Tenant;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        // Get the first tenant
        $tenant = Tenant::first();
        if (!$tenant) {
            $this->command->warn('No tenant found. Skipping CategorySeeder.');
            return;
        }

        $categories = [
            ['id' => 1, 'name' => 'Bebidas'],
            ['id' => 2, 'name' => 'Alimentos'],
            ['id' => 3, 'name' => 'Electrónicos'],
            ['id' => 4, 'name' => 'Alimentos para Mascotas'],
            ['id' => 5, 'name' => 'Cecinas y Embutidos'],
            ['id' => 6, 'name' => 'Despensa'],
            ['id' => 7, 'name' => 'Panaderia y Pasteleria'],
            ['id' => 8, 'name' => 'Chocolates y Galletas'],
            ['id' => 10, 'name' => 'Lacteos'],
            ['id' => 11, 'name' => 'Dulces'],
            ['id' => 12, 'name' => 'Snacks'],
            ['id' => 18, 'name' => 'Limpieza'],
            ['id' => 19, 'name' => 'Frutas y Verduras'],
            ['id' => 20, 'name' => 'Aseo personal'],
            ['id' => 23, 'name' => 'Jugos'],
            ['id' => 24, 'name' => 'Aguas'],
            ['id' => 25, 'name' => 'Cafe'],
            ['id' => 26, 'name' => 'Cereales'],
            ['id' => 27, 'name' => 'Accesorios para Mascotas'],
            ['id' => 47, 'name' => 'Helados'],
            ['id' => 48, 'name' => 'Hielo'],
            ['id' => 49, 'name' => 'Barras de Proteinas'],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['name' => $category['name'], 'tenant_id' => $tenant->id],
                ['tenant_id' => $tenant->id]
            );
        }

        $this->command->info('CategorySeeder: ' . count($categories) . ' categories seeded.');
    }
}

