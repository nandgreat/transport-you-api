<?php

namespace App\Services;

use App\Models\Vehicle;

class VehicleService
{
    public function getVehicles(?int $tenantId, int $perPage = 15)
    {
        return Vehicle::when($tenantId, function ($query, $tenantId) {
            return $query->where('tenant_id', $tenantId);
        })->paginate($perPage);
    }

    public function createVehicle(?int $tenantId, array $data)
    {
        return Vehicle::create(array_merge($data, $tenantId ? ['tenant_id' => $tenantId] : []));
    }

    public function getVehicle(?int $tenantId, string $id)
    {
        return Vehicle::when($tenantId, function ($query, $tenantId) {
            return $query->where('tenant_id', $tenantId);
        })->findOrFail($id);
    }

    public function updateVehicle(?int $tenantId, string $id, array $data)
    {
        $vehicle = $this->getVehicle($tenantId, $id);
        $vehicle->update($data);
        return $vehicle;
    }

    public function deleteVehicle(?int $tenantId, string $id)
    {
        $vehicle = $this->getVehicle($tenantId, $id);
        $vehicle->delete();
        return true;
    }
}
