<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class NandomUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'paul4nank@gmail.com'],
            [
                'first_name'        => 'Nandom',
                'last_name'         => 'Kumchi',
                'phone'             => '07031676998',
                'password'          => Hash::make('Pass1234'),
                'tenant_id'         => null,
                'status'            => 'active',
                'email_verified_at' => now(),
            ]
        );
    }
}
