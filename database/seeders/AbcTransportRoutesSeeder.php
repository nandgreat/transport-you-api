<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Route;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class AbcTransportRoutesSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('email', 'abctransport@gmail.com')->firstOrFail();

        $loc = fn(string $name) => Location::where('name', $name)->whereNull('tenant_id')->firstOrFail();

        $abuja  = $loc('Abuja');
        $lagos  = $loc('Lagos');
        $jos    = $loc('Jos');
        $makurdi = $loc('Makurdi');
        $kano   = $loc('Kano');
        $ph     = $loc('Port Harcourt');

        // distance (km), estimated_duration (hours)
        $routes = [
            [$abuja,   $lagos,   764, 9],
            [$lagos,   $abuja,   764, 9],
            [$abuja,   $jos,     337, 5],
            [$jos,     $abuja,   337, 5],
            [$abuja,   $makurdi, 218, 4],
            [$makurdi, $abuja,   218, 4],
            [$abuja,   $kano,    345, 5],
            [$kano,    $abuja,   345, 5],
            [$abuja,   $ph,      780, 11],
            [$ph,      $abuja,   780, 11],
        ];

        foreach ($routes as [$origin, $destination, $distance, $duration]) {
            Route::updateOrCreate(
                [
                    'tenant_id'      => $tenant->id,
                    'origin_id'      => $origin->id,
                    'destination_id' => $destination->id,
                ],
                [
                    'distance'           => $distance,
                    'estimated_duration' => $duration,
                    'status'             => 'active',
                ]
            );
        }
    }
}
