<?php

namespace App\Services;

use App\Models\SeatArrangementType;

class SeatArrangementTypeService
{
    public function getSeatArrangementTypes(?string $status = null, ?int $vehicleTypeId = null, int $perPage = 50)
    {
        return SeatArrangementType::withCount('vehicleTypes')
            ->when($status, fn ($q, $v) => $q->where('status', $v))
            ->when($vehicleTypeId, fn ($q, $v) => $q->whereHas(
                'vehicleTypes',
                fn ($r) => $r->where('vehicle_types.id', $v)
            ))
            ->orderBy('number_of_seats')
            ->paginate($perPage);
    }

    public function getSeatArrangementType(string $id)
    {
        return SeatArrangementType::withCount('vehicleTypes')->findOrFail($id);
    }

    public function createSeatArrangementType(array $data)
    {
        return SeatArrangementType::create($data);
    }

    public function updateSeatArrangementType(string $id, array $data)
    {
        $type = SeatArrangementType::findOrFail($id);
        $type->update($data);
        return $type;
    }

    public function deleteSeatArrangementType(string $id)
    {
        $type = SeatArrangementType::findOrFail($id);
        $type->delete();
        return true;
    }
}
