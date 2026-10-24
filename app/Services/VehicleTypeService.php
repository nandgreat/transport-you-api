<?php

namespace App\Services;

use App\Models\VehicleType;

class VehicleTypeService
{
    public function getVehicleTypes(?int $categoryId = null, int $perPage = 30)
    {
        return VehicleType::with(['category', 'seatArrangementTypes'])
            ->when($categoryId, fn($q) => $q->where('vehicle_category_id', $categoryId))
            ->paginate($perPage);
    }

    public function getVehicleType(string $id)
    {
        return VehicleType::with(['category', 'seatArrangementTypes'])->findOrFail($id);
    }

    public function createVehicleType(array $data)
    {
        [$ids, $default, $data] = $this->extractArrangements($data);

        $vehicleType = VehicleType::create($data);

        if ($ids !== null) {
            $this->syncArrangements($vehicleType, $ids, $default);
        }

        return $vehicleType->load(['category', 'seatArrangementTypes']);
    }

    public function updateVehicleType(string $id, array $data)
    {
        [$ids, $default, $data] = $this->extractArrangements($data);

        $vehicleType = $this->getVehicleType($id);
        $vehicleType->update($data);

        if ($ids !== null) {
            $this->syncArrangements($vehicleType, $ids, $default);
        } elseif ($default !== null) {
            // Changing only which already-attached arrangement is the default.
            $current = $vehicleType->seatArrangementTypes()->pluck('seat_arrangement_types.id')->all();
            $this->syncArrangements($vehicleType, $current, $default);
        }

        return $vehicleType->load(['category', 'seatArrangementTypes']);
    }

    public function deleteVehicleType(string $id)
    {
        $vehicleType = $this->getVehicleType($id);

        try {
            $vehicleType->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            // Foreign key / restrict violation — the type is still referenced
            // (e.g. by schedules). Return a clean, user-friendly message.
            if (in_array($e->getCode(), ['23503', '23000', '23001'], true)) {
                abort(409, 'This vehicle type is in use by one or more schedules and cannot be deleted.');
            }
            throw $e;
        }

        return true;
    }

    /**
     * Pull the pivot inputs out of the payload so they are not mass-assigned.
     *
     * @return array{0: ?array, 1: ?int, 2: array}
     */
    private function extractArrangements(array $data): array
    {
        $ids     = $data['seat_arrangement_type_ids'] ?? null;
        $default = $data['default_seat_arrangement_type_id'] ?? null;
        unset($data['seat_arrangement_type_ids'], $data['default_seat_arrangement_type_id']);

        return [$ids, $default !== null ? (int) $default : null, $data];
    }

    /**
     * Sync a vehicle type's possible arrangements, flagging exactly one default.
     *
     * @param array<int> $ids
     */
    private function syncArrangements(VehicleType $vehicleType, array $ids, ?int $default): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if ($default !== null && ! in_array($default, $ids, true)) {
            abort(422, 'default_seat_arrangement_type_id must be one of the vehicle type\'s seat arrangements.');
        }

        if ($default === null && $ids) {
            $default = $ids[0];
        }

        $vehicleType->seatArrangementTypes()->sync(
            collect($ids)->mapWithKeys(fn ($id) => [$id => ['is_default' => $id === $default]])->all()
        );
    }
}
