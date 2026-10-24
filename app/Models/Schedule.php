<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Booking;
use App\Models\Tenant;
use App\Models\VehicleType;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'schedule_group_id',
        'return_schedule_id',
        'route_id',
        'vehicle_type_id',
        'seat_arrangement_type_id',
        'departure_time',
        'arrival_time',
        'fare',
        'status',
    ];

    /**
     * The seat arrangement explicitly chosen for this schedule (optional).
     */
    public function seatArrangement()
    {
        return $this->belongsTo(SeatArrangementType::class, 'seat_arrangement_type_id');
    }

    /**
     * The seat arrangement that actually applies: the chosen one, otherwise the
     * vehicle type's default.
     */
    public function resolveSeatArrangement(): ?SeatArrangementType
    {
        return $this->seatArrangement ?? $this->vehicleType?->defaultSeatArrangementType();
    }

    public function seatArrangementLabel(): ?string
    {
        return $this->resolveSeatArrangement()?->name;
    }

    /**
     * Total number of seats for this schedule, taken entirely from the
     * applicable seat arrangement. 0 when none can be resolved.
     */
    public function capacity(): int
    {
        return $this->resolveSeatArrangement()?->number_of_seats ?? 0;
    }

    /**
     * Seat numbers already reserved on this schedule (pending or confirmed).
     *
     * @return array<int>
     */
    public function takenSeats(): array
    {
        return $this->bookings()
            ->whereIn('status', ['pending', 'confirmed'])
            ->get(['passengers'])
            ->flatMap(fn ($b) => collect($b->passengers ?? [])->pluck('seat_number'))
            ->reject(fn ($s) => $s === null || $s === '')
            ->map(fn ($s) => (int) $s)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Full seat map: every seat number and whether it can still be selected.
     *
     * @return array<int, array{number:int, available:bool}>
     */
    public function seatMap(): array
    {
        $capacity = $this->capacity();
        if ($capacity < 1) {
            return [];
        }

        $taken = $this->takenSeats();

        return collect(range(1, $capacity))
            ->map(fn ($n) => ['number' => $n, 'available' => ! in_array($n, $taken, true)])
            ->all();
    }

    protected $casts = [
        'departure_time' => 'datetime',
        'arrival_time' => 'datetime',
        'fare' => 'decimal:2',
    ];

    public function scheduleGroup()
    {
        return $this->belongsTo(ScheduleGroup::class);
    }

    public function returnSchedule()
    {
        return $this->belongsTo(Schedule::class, 'return_schedule_id');
    }

    public function outboundSchedule()
    {
        return $this->hasOne(Schedule::class, 'return_schedule_id');
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    public function vehicleType()
    {
        return $this->belongsTo(VehicleType::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function availableSeats(): int
    {
        return max(0, $this->capacity() - count($this->takenSeats()));
    }
}
