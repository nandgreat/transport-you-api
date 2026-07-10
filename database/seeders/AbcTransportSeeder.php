<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AbcTransportSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $tenant = Tenant::updateOrCreate(
                ['email' => 'abctransport@gmail.com'],
                [
                    'name'   => 'ABC Transport Limited',
                    'phone'  => '09023849288',
                    'status' => 'active',
                ]
            );

            $adminRole = Role::firstOrCreate(
                ['name' => 'admin', 'tenant_id' => $tenant->id]
            );

            $user = User::updateOrCreate(
                ['email' => 'okoroejiofor@gmail.com'],
                [
                    'first_name'        => 'Okoro',
                    'last_name'         => 'Ejiofor',
                    'phone'             => '08034758377',
                    'password'          => Hash::make('Pass1234'),
                    'tenant_id'         => $tenant->id,
                    'status'            => 'active',
                    'email_verified_at' => now(),
                ]
            );

            if (! $user->roles->contains($adminRole->id)) {
                $user->roles()->attach($adminRole->id);
            }
        });
    }
}
