<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SuperAdminSeeder::class,
            StatesSeeder::class,
            StatusesSeeder::class,
            LocationsSeeder::class,
            VehicleCategorySeeder::class,
            SeatArrangementTypeSeeder::class,
            VehicleTypeSeeder::class,
            TenantSeeder::class,
            TenantLocationsSeeder::class,
            TenantRoutesSeeder::class,
            NandomUserSeeder::class,
            AbcTransportSeeder::class,
            AbcTransportRoutesSeeder::class,
            AbcTransportScheduleSeeder::class,
        ]);
    }
}
