<?php

namespace Database\Seeders;

use App\Models\VehicleCategory;
use Illuminate\Database\Seeder;

class VehicleCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Sedan',
            'Mini',
            'Hatchback',
            'SUV',
            'Minivan',
            'Bus',
            'Coaster',
            'Pickup Truck',
            'Crossover',
            'Convertible',
        ];

        foreach ($categories as $name) {
            VehicleCategory::firstOrCreate(['vehicle_category_name' => $name]);
        }
    }
}
