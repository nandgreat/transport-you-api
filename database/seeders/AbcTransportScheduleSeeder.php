<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Route;
use App\Models\Schedule;
use App\Models\Tenant;
use App\Models\VehicleType;
use Illuminate\Database\Seeder;

class AbcTransportScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::findOrFail(2); // ABC Transport Limited

        $abuja = Location::where('name', 'Abuja')->whereNull('tenant_id')->firstOrFail();
        $lagos = Location::where('name', 'Lagos')->whereNull('tenant_id')->firstOrFail();

        $route = Route::where('tenant_id', $tenant->id)
            ->where('origin_id', $abuja->id)
            ->where('destination_id', $lagos->id)
            ->firstOrFail();

        $vehicleType = VehicleType::where('status', 'active')->firstOrFail();

        Schedule::updateOrCreate(
            [
                'tenant_id'      => $tenant->id,
                'route_id'       => $route->id,
                'departure_time' => '2026-07-15 06:00:00',
            ],
            [
                'vehicle_type_id' => $vehicleType->id,
                'arrival_time'    => '2026-07-15 15:00:00',
                'fare'            => 8500.00,
                'status'          => 'scheduled',
            ]
        );
    }
}
