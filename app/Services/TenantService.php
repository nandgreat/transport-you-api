<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Traits\ManagesOtp;

class TenantService
{
    use ManagesOtp;

    public function onboardTenant(array $data)
    {
        $user = DB::transaction(function () use ($data) {
            $tenant = Tenant::create([
                'name' => $data['company_name'],
                'email' => $data['company_email'] ?? null,
                'phone' => $data['company_phone'] ?? null,
                'status' => 'active',
            ]);

            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'phone' => $data['phone'] ?? null,
                'tenant_id' => $tenant->id,
                'status' => 'active',
            ]);

            $adminRole = Role::create([
                'name' => 'admin',
                'tenant_id' => $tenant->id,
            ]);

            $user->roles()->attach($adminRole->id);

            return $user;
        });

        $this->generateAndSendOtp($user);

        return $user;
    }
}
