<?php

namespace App\Services;

use App\Models\VehicleType;

class VehicleTypeService
{
    public function getVehicleTypes(?int $categoryId = null, int $perPage = 30)
    {
        return VehicleType::with('category')
            ->when($categoryId, fn($q) => $q->where('vehicle_category_id', $categoryId))
            ->paginate($perPage);
    }

    public function getVehicleType(string $id)
    {
        return VehicleType::with('category')->findOrFail($id);
    }

    public function createVehicleType(array $data)
    {
        $vehicleType = VehicleType::create($data);
        return $vehicleType->load('category');
    }

    public function updateVehicleType(string $id, array $data)
    {
        $vehicleType = $this->getVehicleType($id);
        $vehicleType->update($data);
        return $vehicleType->load('category');
    }

    public function deleteVehicleType(string $id)
    {
        $vehicleType = $this->getVehicleType($id);
        $vehicleType->delete();
        return true;
    }
}
