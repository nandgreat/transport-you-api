<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $tenant = Tenant::firstOrCreate(
                ['email' => 'admin@fasttravelltd.com'],
                [
                    'name'   => 'Fast Travel Ltd',
                    'phone'  => '08012345678',
                    'status' => 'active',
                ]
            );

            $adminRole = Role::firstOrCreate(
                ['name' => 'admin', 'tenant_id' => $tenant->id]
            );

            $user = User::firstOrCreate(
                ['email' => 'admin@fasttravelltd.com'],
                [
                    'first_name'        => 'John',
                    'last_name'         => 'Doe',
                    'password'          => Hash::make('password'),
                    'phone'             => '08012345678',
                    'tenant_id'         => $tenant->id,
                    'status'            => 'active',
                    'email_verified_at' => now(),
                ]
            );

            if (!$user->roles->contains($adminRole->id)) {
                $user->roles()->attach($adminRole->id);
            }
        });
    }
}
