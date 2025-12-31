<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Supplier;
use App\Models\Tenant;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        // Get the first tenant
        $tenant = Tenant::first();
        if (!$tenant) {
            $this->command->warn('No tenant found. Skipping SupplierSeeder.');
            return;
        }

        $suppliers = [
            'Comtech SpA',
            'Coca Cola Andina',
            'Breden Master',
            'Distribuidores',
            'Global Ve',
            'San Jorge',
            'Arcor',
            'Embutidos Diaz',
            'Master Pan',
            'Soprole',
            'Sabores de mi tierra',
            'Comercializadora Haiti',
            'Fruna',
            'Otros',
            'Inversiones VIA K',
            'Mayvan',
            'Coconutt',
            'Santa Nao Spa',
            'Comercial Los Lagos S.A',
            'El Saco de Paltas',
            'Ideal',
            'Supermercados',
            'Alan Huevos',
            'Biscomund',
            'HyG Distribuciones',
            'Listto',
            'Inversiones Via K SPA',
            'Savory',
            'Las Rosas',
            'Quesilac',
            'Hielo',
            '58 Market',
            'Lennox Mall SPA',
            'Chicharron GO',
            'Practicos Las Rosas',
            'Rolando',
            'La Alianza',
            'Panificadora Marcelo',
            'Comercializadora Jose Zambrano SpA',
            'Comercializadora Alva Spa',
            'Grupo Imperio Spa ALESSANDRINO',
        ];

        foreach ($suppliers as $name) {
            Supplier::updateOrCreate(
                ['name' => $name, 'tenant_id' => $tenant->id],
                ['tenant_id' => $tenant->id]
            );
        }

        $this->command->info('SupplierSeeder: ' . count($suppliers) . ' suppliers seeded.');
    }
}
