<?php

namespace App\Services;

use App\Models\Schedule;

class ScheduleService
{
    public function getSchedules(?int $tenantId, int $perPage = 15)
    {
        return Schedule::with(['route.origin', 'route.destination', 'vehicle'])
            ->when($tenantId, function ($query, $tenantId) {
                return $query->where('tenant_id', $tenantId);
            })->paginate($perPage);
    }

    public function createSchedule(?int $tenantId, array $data)
    {
        return Schedule::create(array_merge($data, $tenantId ? ['tenant_id' => $tenantId] : []));
    }

    public function getSchedule(?int $tenantId, string $id)
    {
        return Schedule::with(['route.origin', 'route.destination', 'vehicle'])
            ->when($tenantId, function ($query, $tenantId) {
                return $query->where('tenant_id', $tenantId);
            })->findOrFail($id);
    }

    public function updateSchedule(?int $tenantId, string $id, array $data)
    {
        $schedule = $this->getSchedule($tenantId, $id);
        $schedule->update($data);
        return $schedule;
    }

    public function deleteSchedule(?int $tenantId, string $id)
    {
        $schedule = $this->getSchedule($tenantId, $id);
        $schedule->delete();
        return true;
    }
}
