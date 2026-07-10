<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\State;
use App\Models\Status;
use Illuminate\Database\Seeder;

class LocationsSeeder extends Seeder
{
    public function run(): void
    {
        $status = Status::firstOrCreate(['name' => 'active']);

        $locations = [
            ['name' => 'Abuja',         'city' => 'Abuja',         'state_code' => 'FC'],
            ['name' => 'Lagos',         'city' => 'Lagos',         'state_code' => 'LA'],
            ['name' => 'Port Harcourt', 'city' => 'Port Harcourt', 'state_code' => 'RI'],
            ['name' => 'Kano',          'city' => 'Kano',          'state_code' => 'KN'],
            ['name' => 'Jos',           'city' => 'Jos',           'state_code' => 'PL'],
            ['name' => 'Kaduna',        'city' => 'Kaduna',        'state_code' => 'KD'],
            ['name' => 'Makurdi',       'city' => 'Makurdi',       'state_code' => 'BE'],
            ['name' => 'Enugu',         'city' => 'Enugu',         'state_code' => 'EN'],
            ['name' => 'Ibadan',        'city' => 'Ibadan',        'state_code' => 'OY'],
            ['name' => 'Onitsha',       'city' => 'Onitsha',       'state_code' => 'AN'],
            ['name' => 'Yola',          'city' => 'Yola',          'state_code' => 'AD'],
            ['name' => 'Ilorin',        'city' => 'Ilorin',        'state_code' => 'KW'],
            ['name' => 'Owerri',        'city' => 'Owerri',        'state_code' => 'IM'],
        ];

        foreach ($locations as $data) {
            $state = State::where('code', $data['state_code'])->firstOrFail();

            Location::updateOrCreate(
                ['name' => $data['name'], 'tenant_id' => null],
                [
                    'city'      => $data['city'],
                    'state_id'  => $state->id,
                    'status_id' => $status->id,
                ]
            );
        }
    }
}
