<?php

namespace App\Services;

use App\Models\Route;

class RouteService
{
    public function getRoutes(?int $tenantId, int $perPage = 15)
    {
        return Route::with(['origin', 'destination'])
            ->when($tenantId, function ($query, $tenantId) {
                return $query->where('tenant_id', $tenantId);
            })->paginate($perPage);
    }

    public function createRoute(?int $tenantId, array $data)
    {
        return Route::create(array_merge($data, $tenantId ? ['tenant_id' => $tenantId] : []));
    }

    public function getRoute(?int $tenantId, string $id)
    {
        return Route::with(['origin', 'destination'])
            ->when($tenantId, function ($query, $tenantId) {
                return $query->where('tenant_id', $tenantId);
            })->findOrFail($id);
    }

    public function updateRoute(?int $tenantId, string $id, array $data)
    {
        $route = $this->getRoute($tenantId, $id);
        $route->update($data);
        return $route;
    }

    public function deleteRoute(?int $tenantId, string $id)
    {
        $route = $this->getRoute($tenantId, $id);
        $route->delete();
        return true;
    }
}
