<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SeatArrangementType\StoreSeatArrangementTypeRequest;
use App\Http\Requests\SeatArrangementType\UpdateSeatArrangementTypeRequest;
use App\Services\SeatArrangementTypeService;
use Illuminate\Http\Request;

class SeatArrangementTypeController extends Controller
{
    public function __construct(protected SeatArrangementTypeService $service)
    {
        // Write operations: super admin only. Read: any authenticated user.
        $this->middleware(function ($request, $next) {
            if (!$request->user()->isSuperAdmin()) {
                return $this->badRequest('Only super admins can manage seat arrangement types.');
            }
            return $next($request);
        })->only(['store', 'update', 'destroy']);
    }

    public function index(Request $request)
    {
        $request->validate([
            'status'          => 'nullable|in:active,inactive',
            'vehicle_type_id' => 'nullable|integer|exists:vehicle_types,id',
        ]);

        $status        = $request->query('status');
        $vehicleTypeId = $request->query('vehicle_type_id') ? (int) $request->query('vehicle_type_id') : null;

        return $this->success(
            $this->service->getSeatArrangementTypes($status, $vehicleTypeId),
            'Seat arrangement types retrieved successfully.'
        );
    }

    public function store(StoreSeatArrangementTypeRequest $request)
    {
        $type = $this->service->createSeatArrangementType($request->validated());
        return $this->created($type, 'Seat arrangement type created successfully.');
    }

    public function show(string $id)
    {
        return $this->success($this->service->getSeatArrangementType($id), 'Seat arrangement type retrieved successfully.');
    }

    public function update(UpdateSeatArrangementTypeRequest $request, string $id)
    {
        $type = $this->service->updateSeatArrangementType($id, $request->validated());
        return $this->success($type, 'Seat arrangement type updated successfully.');
    }

    public function destroy(string $id)
    {
        $this->service->deleteSeatArrangementType($id);
        return $this->success(null, 'Seat arrangement type deleted successfully.');
    }
}
