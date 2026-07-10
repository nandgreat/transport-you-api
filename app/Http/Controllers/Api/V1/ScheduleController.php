<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Schedule\StoreScheduleRequest;
use App\Http\Requests\Schedule\UpdateScheduleRequest;
use App\Models\Schedule;
use App\Services\ScheduleService;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function __construct(protected ScheduleService $scheduleService)
    {
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
        $request->validate([
            'origin_id'        => 'nullable|integer|exists:locations,id',
            'destination_id'   => 'nullable|integer|exists:locations,id',
            'origin_city'      => 'nullable|string|max:100',
            'destination_city' => 'nullable|string|max:100',
            'departure_date'   => 'nullable|date_format:Y-m-d',
            'status'           => 'nullable|string|in:scheduled,in_transit,completed,cancelled',
            'round_trip'       => 'nullable|boolean',
            'tenant_id'        => 'nullable|integer|exists:tenants,id',
        ]);

        $tenantId = $this->resolveTenantId($request, 'tenant_id');
        $filters  = $request->only(['origin_id', 'destination_id', 'origin_city', 'destination_city', 'departure_date', 'status', 'round_trip']);

        $schedules = $this->scheduleService->getSchedules($tenantId, $filters);

        $schedules->setCollection(
            $schedules->getCollection()->map(fn($s) => $this->formatSchedule($s))
        );

        return $this->success($schedules, 'Schedules retrieved successfully');
    }

    public function store(StoreScheduleRequest $request)
    {
        $tenantId = $this->resolveTenantId($request);

        if ($request->filled('start_date')) {
            $result = $this->scheduleService->createRecurringSchedules($tenantId, $request->validated());

            $response = [
                'group'           => $this->formatGroup($result['group']),
                'schedules_count' => $result['schedules']->count(),
                'schedules'       => $result['schedules']->map(fn($s) => $this->formatSchedule(
                    $s->load(['route.origin.state', 'route.destination.state', 'vehicleType.category', 'tenant',
                              'returnSchedule.route.origin.state', 'returnSchedule.route.destination.state', 'returnSchedule.vehicleType.category'])
                )),
            ];

            if ($result['return_group']) {
                $response['return_group'] = $this->formatGroup($result['return_group']);
            }

            return $this->created($response, 'Recurring schedules created successfully');
        }

        $schedule = $this->scheduleService->createSchedule($tenantId, $request->validated());
        return $this->created($this->formatSchedule($schedule), 'Schedule created successfully');
    }

    public function show(Request $request, string $id)
    {
        $tenantId = $this->resolveTenantId($request);
        $schedule = $this->scheduleService->getSchedule($tenantId, $id);
        return $this->success($this->formatSchedule($schedule), 'Schedule retrieved successfully');
    }

    public function update(UpdateScheduleRequest $request, string $id)
    {
        $tenantId = $this->resolveTenantId($request);
        $schedule = $this->scheduleService->updateSchedule($tenantId, $id, $request->validated());
        return $this->success($this->formatSchedule($schedule), 'Schedule updated successfully');
    }

    public function destroy(Request $request, string $id)
    {
        $tenantId = $this->resolveTenantId($request);
        $this->scheduleService->deleteSchedule($tenantId, $id);
        return $this->success(null, 'Schedule deleted successfully');
    }

    private function formatLocation(?\App\Models\Location $location): ?array
    {
        if (! $location) return null;

        return [
            'id'    => $location->id,
            'name'  => $location->name,
            'city'  => $location->city,
            'state' => $location->state?->name,
        ];
    }

    private function formatRoute(?\App\Models\Route $route): ?array
    {
        if (! $route) return null;

        return [
            'id'                 => $route->id,
            'external_ref'       => $route->external_ref,
            'distance'           => $route->distance,
            'estimated_duration' => $route->estimated_duration,
            'origin'             => $this->formatLocation($route->origin),
            'destination'        => $this->formatLocation($route->destination),
        ];
    }

    private function formatGroup(\App\Models\ScheduleGroup $group): array
    {
        $vt = $group->vehicleType;

        return [
            'id'             => $group->id,
            'start_date'     => $group->start_date->format('Y-m-d'),
            'end_date'       => $group->end_date->format('Y-m-d'),
            'days_of_week'   => $group->days_of_week,
            'departure_time' => $group->departure_time,
            'arrival_time'   => $group->arrival_time,
            'fare'           => $group->fare,
            'status'         => $group->status,
            'route'          => $this->formatRoute($group->route),
            'vehicle_type'   => $vt ? [
                'id'           => $vt->id,
                'vehicle_name' => $vt->vehicle_name,
                'capacity'     => $vt->capacity,
                'year'         => $vt->year,
                'category'     => $vt->category,
            ] : null,
            'operator' => $group->tenant ? [
                'id'   => $group->tenant->id,
                'name' => $group->tenant->name,
            ] : null,
        ];
    }

    private function formatSchedule(Schedule $schedule, bool $includeReturn = true): array
    {
        $vt = $schedule->relationLoaded('vehicleType') ? $schedule->vehicleType : null;

        $data = [
            'id'              => $schedule->id,
            'departure_time'  => $schedule->departure_time,
            'arrival_time'    => $schedule->arrival_time,
            'fare'            => $schedule->fare,
            'status'          => $schedule->status,
            'available_seats' => $schedule->availableSeats(),
            'is_round_trip'   => $schedule->return_schedule_id !== null,
            'route'           => $this->formatRoute($schedule->route),
            'vehicle_type'    => $vt ? [
                'id'           => $vt->id,
                'vehicle_name' => $vt->vehicle_name,
                'capacity'     => $vt->capacity,
                'year'         => $vt->year,
                'image_url'    => $vt->image_url,
                'category'     => $vt->category,
            ] : null,
            'operator' => $schedule->relationLoaded('tenant') && $schedule->tenant ? [
                'id'   => $schedule->tenant->id,
                'name' => $schedule->tenant->name,
            ] : null,
        ];

        if ($includeReturn && $schedule->relationLoaded('returnSchedule') && $schedule->returnSchedule) {
            $data['return_trip'] = $this->formatSchedule($schedule->returnSchedule, false);
        } else {
            $data['return_trip'] = null;
        }

        return $data;
    }
}
