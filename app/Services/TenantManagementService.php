<?php

namespace App\Services;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Super-admin management of every tenant company on the platform. Unlike the
 * tenant self-service controller, nothing here is scoped to the caller's own
 * tenant — the super admin acts across all of them.
 */
class TenantManagementService
{
    private const COUNTS = ['users', 'vehicles', 'schedules', 'bookings'];

    public function list(array $filters = [], int $perPage = 15)
    {
        return Tenant::withCount(self::COUNTS)
            ->when($filters['search'] ?? null, function ($q, $term) {
                $like = '%' . $term . '%';
                $q->where(fn ($w) => $w->where('name', 'ilike', $like)->orWhere('email', 'ilike', $like));
            })
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->latest()
            ->paginate($perPage);
    }

    public function get(string $id): Tenant
    {
        return Tenant::withCount(self::COUNTS)->findOrFail($id);
    }

    /**
     * Create a company and, optionally, its first admin user. The admin is
     * created already verified and active — the super admin is vouching for it,
     * so there's no OTP step.
     */
    public function create(array $data): Tenant
    {
        return DB::transaction(function () use ($data) {
            $tenant = Tenant::create([
                'name'   => $data['name'],
                'email'  => $data['email'] ?? null,
                'phone'  => $data['phone'] ?? null,
                'status' => 'active',
            ]);

            if (! empty($data['admin_email'])) {
                $user = User::create([
                    'first_name'        => $data['admin_first_name'],
                    'last_name'         => $data['admin_last_name'],
                    'email'             => $data['admin_email'],
                    'password'          => Hash::make($data['admin_password']),
                    'phone'             => $data['admin_phone'] ?? null,
                    'tenant_id'         => $tenant->id,
                    'status'            => 'active',
                    'email_verified_at' => now(),
                ]);

                $adminRole = Role::create(['name' => 'admin', 'tenant_id' => $tenant->id]);
                $user->roles()->attach($adminRole->id);
            }

            return $this->get((string) $tenant->id);
        });
    }

    public function update(string $id, array $data): Tenant
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->update($data);

        return $this->get((string) $tenant->id);
    }

    /**
     * Delete a company, but only when it carries no operational data — deleting
     * a tenant that still has vehicles, schedules or bookings would orphan them.
     */
    public function delete(string $id): void
    {
        $tenant = Tenant::withCount(self::COUNTS)->findOrFail($id);

        abort_if(
            $tenant->vehicles_count > 0 || $tenant->schedules_count > 0 || $tenant->bookings_count > 0,
            422,
            'This company still has vehicles, schedules or bookings and cannot be deleted. Deactivate it instead.'
        );

        DB::transaction(function () use ($tenant) {
            // Remove the tenant's users and their tenant-scoped roles first.
            foreach ($tenant->users as $user) {
                $user->roles()->detach();
                $user->delete();
            }
            Role::where('tenant_id', $tenant->id)->delete();
            $tenant->delete();
        });
    }
}
