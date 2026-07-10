<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\State;
use App\Models\Status;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class TenantLocationsSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('email', 'admin@fasttravelltd.com')->firstOrFail();
        $status = Status::firstOrCreate(['name' => 'active']);

        $fct   = State::where('code', 'FC')->firstOrFail();
        $lagos = State::where('code', 'LA')->firstOrFail();

        $locations = [
            [
                'tenant_id' => $tenant->id,
                'name'      => 'Nyanya',
                'city'      => 'Abuja',
                'state_id'  => $fct->id,
                'status_id' => $status->id,
            ],
            [
                'tenant_id' => $tenant->id,
                'name'      => 'Ajah',
                'city'      => 'Lagos',
                'state_id'  => $lagos->id,
                'status_id' => $status->id,
            ],
            [
                'tenant_id' => $tenant->id,
                'name'      => 'Jabi',
                'city'      => 'Abuja',
                'state_id'  => $fct->id,
                'status_id' => $status->id,
            ],
        ];

        foreach ($locations as $location) {
            Location::updateOrCreate(
                ['tenant_id' => $location['tenant_id'], 'name' => $location['name']],
                $location
            );
        }
    }
}
