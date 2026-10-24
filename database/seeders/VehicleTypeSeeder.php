<?php

namespace Database\Seeders;

use App\Models\VehicleType;
use Illuminate\Database\Seeder;

class VehicleTypeSeeder extends Seeder
{
    public function run(): void
    {
        $minivan = \App\Models\VehicleCategory::where('vehicle_category_name', 'Minivan')->first();

        $types = [
            [
                'vehicle_name'        => 'Toyota Sienna',
                'vehicle_category_id' => $minivan?->id,
                'image_url'           => 'vehicles/wXlmbGtsjzKhETg7szhoxDSHOmAp7irzOJrLnccg.png',
                'status'              => 'active',
                'year'                => 2005,
                // Possible arrangements for this type; the first is the default.
                'arrangements'        => ['7 Seater', '6 Seater'],
            ],
        ];

        foreach ($types as $type) {
            $arrangements = $type['arrangements'] ?? [];
            unset($type['arrangements']);

            $vehicleType = VehicleType::updateOrCreate(
                ['vehicle_name' => $type['vehicle_name'], 'year' => $type['year']],
                $type
            );

            $ids = \App\Models\SeatArrangementType::whereIn('name', $arrangements)
                ->pluck('id', 'name');

            // Preserve the intended order so the first listed becomes the default.
            $sync = [];
            foreach ($arrangements as $i => $name) {
                if (isset($ids[$name])) {
                    $sync[$ids[$name]] = ['is_default' => $i === 0];
                }
            }
            $vehicleType->seatArrangementTypes()->sync($sync);
        }
    }
}
