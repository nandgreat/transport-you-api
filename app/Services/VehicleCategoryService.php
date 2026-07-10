<?php

namespace App\Services;

use App\Models\VehicleCategory;

class VehicleCategoryService
{
    public function getCategories(int $perPage = 50)
    {
        return VehicleCategory::withCount('vehicleTypes')->paginate($perPage);
    }

    public function getCategory(string $id)
    {
        return VehicleCategory::withCount('vehicleTypes')->findOrFail($id);
    }

    public function createCategory(array $data)
    {
        return VehicleCategory::create($data);
    }

    public function updateCategory(string $id, array $data)
    {
        $category = $this->getCategory($id);
        $category->update($data);
        return $category;
    }

    public function deleteCategory(string $id)
    {
        $category = $this->getCategory($id);
        $category->delete();
        return true;
    }
}
