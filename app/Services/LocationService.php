<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Status;

class LocationService
{
    public function getLocations(array|int $filters = [], int $perPage = 30)
    {
        if (is_int($filters)) {
            $perPage = $filters;
            $filters = [];
        }

        $query = Location::with('state');

        $searchTerm = trim((string) ($filters['search'] ?? $filters['q'] ?? $filters['query'] ?? ''));
        if ($searchTerm !== '') {
            $term = strtolower($searchTerm);
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"])
                  ->orWhereRaw('LOWER(city) LIKE ?', ["%{$term}%"]);
            });
        }

        $city = trim((string) ($filters['city'] ?? ''));
        if ($city !== '') {
            $city = strtolower($city);
            $query->whereRaw('LOWER(city) LIKE ?', ["%{$city}%"]);
        }

        $name = trim((string) ($filters['name'] ?? $filters['location_name'] ?? ''));
        if ($name !== '') {
            $name = strtolower($name);
            $query->whereRaw('LOWER(name) LIKE ?', ["%{$name}%"]);
        }

        if (array_key_exists('tenant_id', $filters) && $filters['tenant_id'] !== null) {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        return $query->latest()->paginate($perPage);
    }

    public function createLocation(array $data)
    {
        if (empty($data['status_id'])) {
            $defaultStatus = Status::firstOrCreate(['name' => 'active']);
            $data['status_id'] = $defaultStatus->id;
        }

        return Location::create($data)->load('state');
    }

    public function getLocation(?int $tenantId, string $id)
    {
        return Location::with('state')
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->findOrFail($id);
    }

    public function updateLocation(?int $tenantId, string $id, array $data)
    {
        $location = $this->getLocation($tenantId, $id);

        // Map the active/inactive status name onto its status_id.
        if (array_key_exists('status', $data)) {
            if (! empty($data['status'])) {
                $data['status_id'] = Status::firstOrCreate(['name' => $data['status']])->id;
            }
            unset($data['status']);
        }

        $location->update($data);

        return $location->load('state');
    }

    public function deleteLocation(?int $tenantId, string $id)
    {
        $location = $this->getLocation($tenantId, $id);
        $location->delete();

        return true;
    }
}
