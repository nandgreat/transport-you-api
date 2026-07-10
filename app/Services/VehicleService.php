<?php

namespace App\Services;

use App\Models\Vehicle;

class VehicleService
{
    public function getVehicles(?int $tenantId, int $perPage = 15)
    {
        return Vehicle::with(['vehicleType.category'])
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->paginate($perPage);
    }

    public function createVehicle(?int $tenantId, array $data)
    {
        $vehicle = Vehicle::create(array_merge($data, $tenantId ? ['tenant_id' => $tenantId] : []));
        return $vehicle->load(['vehicleType.category']);
    }

    public function getVehicle(?int $tenantId, string $id)
    {
        return Vehicle::with(['vehicleType.category'])
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->findOrFail($id);
    }

    public function updateVehicle(?int $tenantId, string $id, array $data)
    {
        $vehicle = $this->getVehicle($tenantId, $id);
        $vehicle->update($data);
        return $vehicle->load(['vehicleType.category']);
    }

    public function deleteVehicle(?int $tenantId, string $id)
    {
        $vehicle = $this->getVehicle($tenantId, $id);
        $vehicle->delete();
        return true;
    }
}
