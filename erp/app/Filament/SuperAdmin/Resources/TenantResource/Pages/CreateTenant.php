<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Resources\TenantResource\Pages;

use App\Filament\SuperAdmin\Resources\TenantResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Remove admin fields from tenant data
        unset($data['admin_name'], $data['admin_email'], $data['admin_password']);

        return $data;
    }

    protected function afterCreate(): void
    {
        // Get the form data
        $data = $this->data;

        // Create the admin user for this tenant
        $user = User::create([
            'name' => $data['admin_name'],
            'email' => $data['admin_email'],
            'password' => Hash::make($data['admin_password']),
            'tenant_id' => $this->record->id,
            'email_verified_at' => now(),
        ]);

        // Assign tenant_admin role
        $user->assignRole('tenant_admin');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
