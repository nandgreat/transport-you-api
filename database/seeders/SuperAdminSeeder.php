<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::firstOrCreate(
            ['name' => 'super_admin', 'tenant_id' => null]
        );

        $user = User::firstOrCreate(
            ['email' => 'super@admin.com'],
            [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'tenant_id' => null,
                'status' => 'active',
            ]
        );

        if (!$user->roles->contains($role->id)) {
            $user->roles()->attach($role->id);
        }
    }
}
