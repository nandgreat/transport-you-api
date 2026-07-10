<?php

namespace Database\Seeders;

use App\Models\VehicleType;
use Illuminate\Database\Seeder;

class VehicleTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'vehicle_name'        => 'Toyota Sienna',
                'vehicle_category_id' => 5, // Minivan
                'capacity'            => 14,
                'image_url'           => 'vehicles/wXlmbGtsjzKhETg7szhoxDSHOmAp7irzOJrLnccg.png',
                'status'              => 'active',
                'year'                => 2005,
            ],
        ];

        foreach ($types as $type) {
            VehicleType::updateOrCreate(
                ['vehicle_name' => $type['vehicle_name'], 'year' => $type['year']],
                $type
            );
        }
    }
}
