<?php

declare(strict_types=1);

namespace App\Filament\SuperAdmin\Widgets;

use App\Models\Tenant;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $activeTenants = Tenant::where('status', 'active')->count();
        $totalTenants = Tenant::count();
        $totalUsers = User::whereNotNull('tenant_id')->count();
        $superAdmins = User::whereNull('tenant_id')->count();

        return [
            Stat::make('Tiendas Activas', $activeTenants)
                ->description("De {$totalTenants} totales")
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color('success')
                ->chart([7, 3, 4, 5, 6, 3, $activeTenants]),

            Stat::make('Usuarios de Tiendas', $totalUsers)
                ->description('Usuarios registrados en tiendas')
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),

            Stat::make('Super Admins', $superAdmins)
                ->description('Administradores globales')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color('warning'),

            Stat::make('Tiendas Suspendidas', Tenant::where('status', 'suspended')->count())
                ->description('Requieren atención')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),
        ];
    }
}
