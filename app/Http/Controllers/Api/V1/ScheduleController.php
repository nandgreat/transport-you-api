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

        // Unified management list: standalone one-off schedules + recurring
        // groups, collapsed into a single table.
        $rows = collect();

        foreach ($this->scheduleService->getStandaloneSchedules($tenantId, $filters) as $s) {
            $rows->push(['type' => 'one_off'] + $this->formatSchedule($s));
        }

        foreach ($this->scheduleService->getScheduleGroups($tenantId, $filters) as $g) {
            $rows->push(['type' => 'recurring'] + $this->formatGroup($g));
        }

        // Newest first across both sources.
        $rows = $rows->sortByDesc(fn($r) => (string) ($r['created_at'] ?? ''))->values();

        $perPage = 15;
        $page    = max(1, (int) $request->input('page', 1));
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return $this->success($paginator, 'Schedules retrieved successfully');
    }

    public function store(StoreScheduleRequest $request)
    {
        $tenantId = $this->resolveTenantId($request);

        if ($request->filled('start_date')) {
            $result = $this->scheduleService->createRecurringSchedules($tenantId, $request->validated());

            $response = ['group' => $this->formatGroup($result['group'])];

            if ($result['return_group']) {
                $response['return_group'] = $this->formatGroup($result['return_group']);
            }

            return $this->created($response, 'Recurring schedule pattern created successfully');
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

    private function formatVehicleType(?\App\Models\VehicleType $vt): ?array
    {
        if (! $vt) return null;

        return [
            'id'                => $vt->id,
            'vehicle_name'      => $vt->vehicle_name,
            'year'              => $vt->year,
            'image_url'         => $vt->image_url,
            'category'          => $vt->category,
            'seat_arrangements' => $vt->relationLoaded('seatArrangementTypes')
                ? $vt->seatArrangementTypes->map(fn ($sa) => $this->formatSeatArrangement($sa, (bool) $sa->pivot->is_default))->values()
                : [],
        ];
    }

    private function formatSeatArrangement(?\App\Models\SeatArrangementType $sa, ?bool $isDefault = null): ?array
    {
        if (! $sa) return null;

        $out = [
            'id'                => $sa->id,
            'name'              => $sa->name,
            'number_of_seats'   => $sa->number_of_seats,
            'arrangement_image' => $sa->arrangement_image,
            'description'       => $sa->description,
        ];

        if ($isDefault !== null) {
            $out['is_default'] = $isDefault;
        }

        return $out;
    }

    private function formatGroup(\App\Models\ScheduleGroup $group): array
    {
        $vt = $group->vehicleType;

        return [
            'id'             => $group->id,
            'start_date'     => $group->start_date->format('Y-m-d'),
            'end_date'       => $group->end_date->format('Y-m-d'),
            'schedule_end_date' => $group->end_date->format('Y-m-d'),
            'days_of_week'   => $group->days_of_week,
            'departure_time' => $group->departure_time,
            'arrival_time'   => $group->arrival_time,
            'fare'           => $group->fare,
            'status'         => $group->status,
            'is_round_trip'  => false,
            'created_at'     => $group->created_at?->toISOString() ?? (string) $group->created_at,
            'updated_at'     => $group->updated_at?->toISOString() ?? (string) $group->updated_at,
            'seat_selection_enabled' => (bool) $group->seat_selection_enabled,
            'capacity'       => ($group->seatArrangement ?? $vt?->defaultSeatArrangementType())?->number_of_seats,
            'seat_arrangement' => $this->formatSeatArrangement($group->seatArrangement ?? $vt?->defaultSeatArrangementType()),
            'route'          => $this->formatRoute($group->route),
            'vehicle_type'   => $this->formatVehicleType($vt),
            'operator' => $group->tenant ? [
                'id'   => $group->tenant->id,
                'name' => $group->tenant->name,
                'logo_url' => $group->tenant->logo_url,
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
            'seat_arrangement'       => $this->formatSeatArrangement($schedule->resolveSeatArrangement()),
            'seat_arrangement_label' => $schedule->seatArrangementLabel(),
            'seat_selection_enabled' => (bool) $schedule->seat_selection_enabled,
            'capacity'        => $schedule->capacity(),
            'available_seats' => $schedule->availableSeats(),
            'seats'           => $schedule->seat_selection_enabled ? $schedule->seatMap() : [],
            'is_round_trip'   => $schedule->return_schedule_id !== null,
            'route'           => $this->formatRoute($schedule->route),
            'vehicle_type'    => $this->formatVehicleType($vt),
            'operator' => $schedule->relationLoaded('tenant') && $schedule->tenant ? [
                'id'   => $schedule->tenant->id,
                'name' => $schedule->tenant->name,
                'logo_url' => $schedule->tenant->logo_url,
            ] : null,
            'created_at'        => $schedule->created_at?->toISOString() ?? (string) $schedule->created_at,
            'updated_at'        => $schedule->updated_at?->toISOString() ?? (string) $schedule->updated_at,
            'days_of_week'      => $schedule->scheduleGroup?->days_of_week ?? null,
            'schedule_end_date' => $schedule->scheduleGroup?->end_date?->format('Y-m-d') ?? ($schedule->arrival_time ? $schedule->arrival_time->format('Y-m-d') : null),
            'schedule_group_id' => $schedule->schedule_group_id,
            'schedule_group'    => $schedule->relationLoaded('scheduleGroup') && $schedule->scheduleGroup ? $this->formatGroup($schedule->scheduleGroup) : null,
        ];

        if ($includeReturn && $schedule->relationLoaded('returnSchedule') && $schedule->returnSchedule) {
            $data['return_trip'] = $this->formatSchedule($schedule->returnSchedule, false);
        } else {
            $data['return_trip'] = null;
        }

        return $data;
    }
}
