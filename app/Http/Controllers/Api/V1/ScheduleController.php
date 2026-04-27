<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Schedule\StoreScheduleRequest;
use App\Http\Requests\Schedule\UpdateScheduleRequest;
use App\Services\ScheduleService;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    protected $scheduleService;

    public function __construct(ScheduleService $scheduleService)
    {
        $this->scheduleService = $scheduleService;

        $this->middleware(function ($request, $next) {
            if (!$request->user()) {
                return $this->notFound('Unauthorized.');
            }
            if (!$request->user()->isSuperAdmin() && !$request->user()->tenant_id) {
                return $this->badRequest('Unauthorized. Must belong to a tenant.');
            }
            return $next($request);
        });
    }

    private function resolveTenantId(Request $request, ?string $inputKey = 'tenant_id'): ?int
    {
        if ($request->user()->isSuperAdmin()) {
            return $request->input($inputKey) ? (int) $request->input($inputKey) : null;
        }
        return $request->user()->tenant_id;
    }

    public function index(Request $request)
    {
        $tenantId = $this->resolveTenantId($request, 'tenant_id');
        return $this->success($this->scheduleService->getSchedules($tenantId), 'Schedules retrieved successfully');
    }

    public function store(StoreScheduleRequest $request)
    {
        $tenantId = $this->resolveTenantId($request);
        $schedule = $this->scheduleService->createSchedule($tenantId, $request->validated());
        return $this->created($schedule, 'Schedule created successfully');
    }

    public function show(Request $request, string $id)
    {
        $tenantId = $this->resolveTenantId($request);
        return $this->success($this->scheduleService->getSchedule($tenantId, $id), 'Schedule retrieved successfully');
    }

    public function update(UpdateScheduleRequest $request, string $id)
    {
        $tenantId = $this->resolveTenantId($request);
        $schedule = $this->scheduleService->updateSchedule($tenantId, $id, $request->validated());
        return $this->success($schedule, 'Schedule updated successfully');
    }

    public function destroy(Request $request, string $id)
    {
        $tenantId = $this->resolveTenantId($request);
        $this->scheduleService->deleteSchedule($tenantId, $id);
        return $this->success(null, 'Schedule deleted successfully');
    }
}
