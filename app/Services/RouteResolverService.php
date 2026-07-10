<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Route;
use Illuminate\Validation\ValidationException;

class RouteResolverService
{
    /**
     * Resolve a route_id from the given input data.
     *
     * Accepts one of three forms (checked in order):
     *   1. route_id                                    — use it directly
     *   2. origin_id + destination_id                  — find or create route
     *   3. origin_city + destination_city (+ tenant_id) — resolve locations by city name, then find or create route
     *
     * Returns the resolved route_id.
     */
    public function resolve(int $tenantId, array $data): int
    {
        if (!empty($data['route_id'])) {
            return (int) $data['route_id'];
        }

        [$originId, $destinationId] = $this->resolveLocationIds($tenantId, $data);

        $route = Route::firstOrCreate(
            ['tenant_id' => $tenantId, 'origin_id' => $originId, 'destination_id' => $destinationId],
            array_filter([
                'external_ref'       => $data['external_route_ref'] ?? null,
                'distance'           => $data['distance'] ?? null,
                'estimated_duration' => $data['estimated_duration'] ?? null,
            ])
        );

        return $route->id;
    }

    private function resolveLocationIds(int $tenantId, array $data): array
    {
        if (!empty($data['origin_id']) && !empty($data['destination_id'])) {
            return [(int) $data['origin_id'], (int) $data['destination_id']];
        }

        if (!empty($data['origin_city']) && !empty($data['destination_city'])) {
            $origin = $this->findLocationByCity($tenantId, $data['origin_city']);
            $dest   = $this->findLocationByCity($tenantId, $data['destination_city']);

            return [$origin->id, $dest->id];
        }

        throw ValidationException::withMessages([
            'route' => 'Provide route_id, origin_id + destination_id, or origin_city + destination_city.',
        ]);
    }

    private function findLocationByCity(int $tenantId, string $city): Location
    {
        $location = Location::where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            })
            ->whereRaw('LOWER(city) = ?', [strtolower(trim($city))])
            ->first();

        if (!$location) {
            throw ValidationException::withMessages([
                'route' => "No location found for city \"{$city}\". Register the location first or use origin_id/destination_id.",
            ]);
        }

        return $location;
    }
}
