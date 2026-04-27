<?php

namespace App\Services;

use App\Models\Location;

class LocationService
{
    public function getLocations(int $perPage = 30)
    {
        return Location::with('state')->paginate($perPage);
    }

    public function createLocation(array $data)
    {
        logInfo($data);
        return Location::create(array_merge($data));
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
