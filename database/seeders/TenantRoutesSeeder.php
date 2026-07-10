<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Route;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class TenantRoutesSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('email', 'admin@fasttravelltd.com')->firstOrFail();

        $nyanya = Location::where('tenant_id', $tenant->id)->where('name', 'Nyanya')->firstOrFail();
        $ajah   = Location::where('tenant_id', $tenant->id)->where('name', 'Ajah')->firstOrFail();
        $jabi   = Location::where('tenant_id', $tenant->id)->where('name', 'Jabi')->firstOrFail();

        $routes = [
            [
                'tenant_id'          => $tenant->id,
                'origin_id'          => $nyanya->id,
                'destination_id'     => $ajah->id,
                'distance'           => 564.00,
                'estimated_duration' => 12,
                'status'             => 'active',
            ],
            [
                'tenant_id'          => $tenant->id,
                'origin_id'          => $ajah->id,
                'destination_id'     => $nyanya->id,
                'distance'           => 564.00,
                'estimated_duration' => 12,
                'status'             => 'active',
            ],
            [
                'tenant_id'          => $tenant->id,
                'origin_id'          => $jabi->id,
                'destination_id'     => $ajah->id,
                'distance'           => 550.00,
                'estimated_duration' => 11,
                'status'             => 'active',
            ],
            [
                'tenant_id'          => $tenant->id,
                'origin_id'          => $ajah->id,
                'destination_id'     => $jabi->id,
                'distance'           => 550.00,
                'estimated_duration' => 11,
                'status'             => 'active',
            ],
        ];

        foreach ($routes as $route) {
            Route::updateOrCreate(
                [
                    'tenant_id'      => $route['tenant_id'],
                    'origin_id'      => $route['origin_id'],
                    'destination_id' => $route['destination_id'],
                ],
                $route
            );
        }
    }
}
