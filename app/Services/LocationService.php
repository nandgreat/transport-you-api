<?php

namespace App\Services;

use App\Models\Location;

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

        if (!empty($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        return $query->paginate($perPage);
    }

    public function createLocation(array $data)
    {
        if (empty($data['status_id'])) {
            $defaultStatus = \App\Models\Status::firstOrCreate(['name' => 'active']);
            $data['status_id'] = $defaultStatus->id;
        }
        return Location::create($data);
    }

    public function getLocation(string $id)
    {
        return Location::when(function ($query) {
            return $query;
        })->findOrFail($id);
    }

    public function updateLocation(string $id, array $data)
    {
        $location = $this->getLocation($id);
        $location->update($data);
        return $location;
    }

    public function deleteLocation(string $id)
    {
        $location = $this->getLocation($id);
        $location->delete();
        return true;
    }
}
