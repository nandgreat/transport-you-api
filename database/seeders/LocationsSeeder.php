<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class LocationsSeeder extends Seeder
{
    public function run(): void
    {

        $locations = [
            ['name' => 'Abuja', 'city' => 'Abuja', 'state_id' => 15],
            ['name' => 'Lagos', 'city' => 'Lagos', 'state_id' => 25],
            ['name' => 'Port Harcourt', 'city' => 'Port Harcourt', 'state_id' => 33],
            ['name' => 'Kano', 'city' => 'Kano', 'state_id' => 20],
            ['name' => 'Jos', 'city' => 'Jos', 'state_id' => 32],
            ['name' => 'Kaduna', 'city' => 'Kaduna', 'state_id' => 19],
            ['name' => 'Makurdi', 'city' => 'Makurdi', 'state_id' => 7],
            ['name' => 'Yola', 'city' => 'Yola', 'state_id' => 2],
            ['name' => 'Enugu', 'city' => 'Enugu', 'state_id' => 14],
            ['name' => 'Ibadan', 'city' => 'Ibadan', 'state_id' => 31],
            ['name' => 'Ilorin', 'city' => 'Ilorin', 'state_id' => 24],
            ['name' => 'Owerri', 'city' => 'Owerri', 'state_id' => 17],
        ];

        foreach ($locations as $location) {
            Location::updateOrCreate(
                ['name' => $location['name']],
                array_merge($location, ['status_id' => 1])
            );
        }
    }
}
