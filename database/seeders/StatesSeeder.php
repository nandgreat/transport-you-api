<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StatesSeeder extends Seeder
{
    public function run(): void
    {
        $states = [
            ['name' => 'Abia',                'code' => 'AB'],
            ['name' => 'Adamawa',             'code' => 'AD'],
            ['name' => 'Akwa Ibom',           'code' => 'AK'],
            ['name' => 'Anambra',             'code' => 'AN'],
            ['name' => 'Bauchi',              'code' => 'BA'],
            ['name' => 'Bayelsa',             'code' => 'BY'],
            ['name' => 'Benue',               'code' => 'BE'],
            ['name' => 'Borno',               'code' => 'BO'],
            ['name' => 'Cross River',         'code' => 'CR'],
            ['name' => 'Delta',               'code' => 'DE'],
            ['name' => 'Ebonyi',              'code' => 'EB'],
            ['name' => 'Edo',                 'code' => 'ED'],
            ['name' => 'Ekiti',               'code' => 'EK'],
            ['name' => 'Enugu',               'code' => 'EN'],
            ['name' => 'Federal Capital Territory', 'code' => 'FC'],
            ['name' => 'Gombe',               'code' => 'GO'],
            ['name' => 'Imo',                 'code' => 'IM'],
            ['name' => 'Jigawa',              'code' => 'JI'],
            ['name' => 'Kaduna',              'code' => 'KD'],
            ['name' => 'Kano',                'code' => 'KN'],
            ['name' => 'Katsina',             'code' => 'KT'],
            ['name' => 'Kebbi',               'code' => 'KE'],
            ['name' => 'Kogi',                'code' => 'KO'],
            ['name' => 'Kwara',               'code' => 'KW'],
            ['name' => 'Lagos',               'code' => 'LA'],
            ['name' => 'Nasarawa',            'code' => 'NA'],
            ['name' => 'Niger',               'code' => 'NI'],
            ['name' => 'Ogun',                'code' => 'OG'],
            ['name' => 'Ondo',                'code' => 'ON'],
            ['name' => 'Osun',                'code' => 'OS'],
            ['name' => 'Oyo',                 'code' => 'OY'],
            ['name' => 'Plateau',             'code' => 'PL'],
            ['name' => 'Rivers',              'code' => 'RI'],
            ['name' => 'Sokoto',              'code' => 'SO'],
            ['name' => 'Taraba',              'code' => 'TA'],
            ['name' => 'Yobe',                'code' => 'YO'],
            ['name' => 'Zamfara',             'code' => 'ZA'],
        ];

        foreach ($states as $state) {
            DB::table('states')->updateOrInsert(
                ['code' => $state['code']],
                array_merge($state, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
