<?php

namespace Database\Seeders;

use App\Models\SeatArrangementType;
use Illuminate\Database\Seeder;

class SeatArrangementTypeSeeder extends Seeder
{
    public function run(): void
    {
        // Rename earlier placeholder entries in place so existing IDs / FKs survive.
        SeatArrangementType::where('name', 'Mini-bus 5 Seater')->update(['name' => '5 Seater']);
        SeatArrangementType::where('name', '7 Seater Arrangement')->update(['name' => '7 Seater']);

        $types = [
            ['name' => 'Sedan 3 Seater', 'number_of_seats' => 3,  'arrangement_image' => 'seat-arrangements/sedan-3.png', 'description' => '3 seater sedan, excluding the driver.'],
            ['name' => '5 Seater',       'number_of_seats' => 5,  'arrangement_image' => 'seat-arrangements/mpv-5.png',   'description' => 'MPV: driver + 5 passengers (front 1, middle 2, rear 2).'],
            ['name' => '6 Seater',       'number_of_seats' => 6,  'arrangement_image' => 'seat-arrangements/mpv-6.png',   'description' => 'MPV: driver + 6 passengers (front 1, middle 2, rear 3).'],
            ['name' => '7 Seater',       'number_of_seats' => 7,  'arrangement_image' => 'seat-arrangements/mpv-7.png',   'description' => 'MPV: driver + 7 passengers (front 1, middle 3, rear 3).'],
            ['name' => 'Bus 14 Seater',  'number_of_seats' => 14, 'arrangement_image' => 'seat-arrangements/bus-14.png',  'description' => '14 seater bus.'],
            ['name' => 'Bus 18 Seater',  'number_of_seats' => 18, 'arrangement_image' => 'seat-arrangements/bus-18.png',  'description' => '18 seater bus.'],
            ['name' => 'Bus 30 Seater',  'number_of_seats' => 30, 'arrangement_image' => 'seat-arrangements/bus-30.png',  'description' => '30 seater bus.'],
        ];

        foreach ($types as $type) {
            SeatArrangementType::updateOrCreate(
                ['name' => $type['name']],
                array_merge($type, ['status' => 'active']),
            );
        }
    }
}
