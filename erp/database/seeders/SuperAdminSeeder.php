<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create superadmin user
        $superadmin = User::firstOrCreate(
            ['email' => 'admin@erp.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'tenant_id' => null, // No tenant = superadmin
                'email_verified_at' => now(),
            ]
        );

        // Assign superadmin role
        $superadmin->assignRole('superadmin');

        $this->command->info('Superadmin created: admin@erp.com / password');
    }
}
