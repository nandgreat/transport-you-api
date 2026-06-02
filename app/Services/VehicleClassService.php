<?php

namespace App\Services;

use App\Models\VehicleClass;

class VehicleClassService
{
    public function getVehicleClasses(int $perPage = 30)
    {
        return VehicleClass::with(['tenant', 'status'])->paginate($perPage);
    }

    public function createVehicleClass(array $data)
    {
        return VehicleClass::create($data);
    }

    public function getVehicleClass(string $id)
    {
        return VehicleClass::with(['tenant', 'status'])->findOrFail($id);
    }

    public function updateVehicleClass(string $id, array $data)
    {
        $vehicleClass = $this->getVehicleClass($id);
        $vehicleClass->update($data);
        return $vehicleClass;
    }

    public function deleteVehicleClass(string $id)
    {
        $vehicleClass = $this->getVehicleClass($id);
        $vehicleClass->delete();
        return true;
    }
}
