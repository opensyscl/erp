<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Tenant sales channel - authorize only users belonging to this tenant
Broadcast::channel('tenant.{tenantId}.sales', function ($user, $tenantId) {
    return $user->tenant_id === (int) $tenantId;
});
