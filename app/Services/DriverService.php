<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\Schedule;
use Illuminate\Support\Collection;

class DriverService
{
    /**
     * The trips this driver is linked to, limited to the three lifecycle
     * buckets the admin cares about: assigned (not yet started), ongoing, and
     * completed. Cancelled trips are excluded. Newest departure first.
     */
    public function getDriverTrips(?int $tenantId, string $id): Collection
    {
        $driver = $this->getDriver($tenantId, $id);

        return Schedule::with([
                'route.origin', 'route.destination', 'vehicle', 'vehicleType',
                'bookings' => fn ($q) => $q->where('status', 'confirmed'),
            ])
            ->where('driver_id', $driver->id)
            ->whereIn('trip_status', ['not_started', 'ongoing', 'completed'])
            ->orderByDesc('departure_time')
            ->get();
    }

    public function getDrivers(?int $tenantId, int $perPage = 15)
    {
        return Driver::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function createDriver(?int $tenantId, array $data)
    {
        $data['status'] = $data['status'] ?? 'active';
        return Driver::create(array_merge($data, $tenantId ? ['tenant_id' => $tenantId] : []));
    }

    public function getDriver(?int $tenantId, string $id)
    {
        return Driver::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->findOrFail($id);
    }

    public function updateDriver(?int $tenantId, string $id, array $data)
    {
        $driver = $this->getDriver($tenantId, $id);
        $driver->update($data);
        return $driver;
    }

    public function deleteDriver(?int $tenantId, string $id)
    {
        $driver = $this->getDriver($tenantId, $id);
        $driver->delete();
        return true;
    }
}
