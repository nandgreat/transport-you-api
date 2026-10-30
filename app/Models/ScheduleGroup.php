<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScheduleGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'route_id',
        'vehicle_type_id',
        'seat_arrangement_type_id',
        'seat_selection_enabled',
        'start_date',
        'end_date',
        'days_of_week',
        'departure_time',
        'arrival_time',
        'fare',
        'status',
    ];

    protected $casts = [
        'start_date'   => 'date',
        'end_date'     => 'date',
        'days_of_week' => 'array',
        'fare'         => 'decimal:2',
        'seat_selection_enabled' => 'boolean',
    ];

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

    public function seatArrangement()
    {
        return $this->belongsTo(SeatArrangementType::class, 'seat_arrangement_type_id');
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }

    // ---------------------------------------------------------------------
    // Recurring pattern behaviour
    // ---------------------------------------------------------------------

    public function resolveSeatArrangement(): ?SeatArrangementType
    {
        return $this->seatArrangement ?? $this->vehicleType?->defaultSeatArrangementType();
    }

    public function capacity(): int
    {
        return $this->resolveSeatArrangement()?->number_of_seats ?? 0;
    }

    /** Lower-cased selected weekdays, e.g. ['monday','friday']. */
    public function selectedDays(): array
    {
        return collect($this->days_of_week ?? [])->map(fn ($d) => strtolower($d))->all();
    }

    /** Does this pattern run on the given date (in range and matching weekday)? */
    public function occursOn(Carbon $date): bool
    {
        $date = $date->copy()->startOfDay();

        return $date->betweenIncluded($this->start_date->copy()->startOfDay(), $this->end_date->copy()->startOfDay())
            && in_array(strtolower($date->englishDayOfWeek), $this->selectedDays(), true);
    }

    /**
     * Matching occurrence dates within [from, to] clamped to the pattern range.
     *
     * @return \Illuminate\Support\Collection<int, Carbon>
     */
    public function occurrenceDatesBetween(Carbon $from, Carbon $to): \Illuminate\Support\Collection
    {
        $start = $from->copy()->startOfDay()->max($this->start_date->copy()->startOfDay());
        $end   = $to->copy()->startOfDay()->min($this->end_date->copy()->startOfDay());

        $dates = collect();
        if ($start->gt($end)) {
            return $dates;
        }

        foreach (CarbonPeriod::create($start, $end) as $date) {
            if (in_array(strtolower($date->englishDayOfWeek), $this->selectedDays(), true)) {
                $dates->push($date->copy());
            }
        }

        return $dates;
    }

    /** The next occurrence on or after $from (default today), within range. */
    public function nextOccurrence(?Carbon $from = null): ?Carbon
    {
        $from = ($from ?? Carbon::today())->copy()->startOfDay();

        return $this->occurrenceDatesBetween($from, $this->end_date->copy()->startOfDay())->first();
    }

    public function departureAt(Carbon $date): Carbon
    {
        return Carbon::parse($date->format('Y-m-d') . ' ' . $this->departure_time);
    }

    public function arrivalAt(Carbon $date): ?Carbon
    {
        if (! $this->arrival_time) {
            return null;
        }
        $arrival   = Carbon::parse($date->format('Y-m-d') . ' ' . $this->arrival_time);
        $departure = $this->departureAt($date);
        if ($arrival->lte($departure)) {
            $arrival->addDay();
        }
        return $arrival;
    }

    /** A concrete schedule already materialised for this pattern on $date, if any. */
    public function materializedScheduleFor(string $date): ?Schedule
    {
        return $this->schedules()
            ->whereDate('departure_time', $date)
            ->first();
    }

    public function bookedSeatCountFor(string $date): int
    {
        return $this->materializedScheduleFor($date)?->bookedSeatCount() ?? 0;
    }

    public function availableSeatsFor(string $date): int
    {
        return max(0, $this->capacity() - $this->bookedSeatCountFor($date));
    }

    /**
     * Seat map for a given date: seats taken by any booking already made on a
     * materialised instance are marked unavailable.
     *
     * @return array<int, array{number:int, available:bool}>
     */
    public function seatMapFor(string $date): array
    {
        $capacity = $this->capacity();
        if ($capacity < 1) {
            return [];
        }

        $taken = $this->materializedScheduleFor($date)?->takenSeats() ?? [];

        return collect(range(1, $capacity))
            ->map(fn ($n) => ['number' => $n, 'available' => ! in_array($n, $taken, true)])
            ->all();
    }
}
