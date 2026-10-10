<?php

namespace App\Services;

use App\Models\CheckIn;
use App\Models\Child;
use App\Models\Room;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckInService
{
    public function checkIn(Tenant $tenant, int $roomId, int $childId): CheckIn
    {
        return DB::transaction(function () use ($tenant, $roomId, $childId) {
            $child = Child::where('tenant_id', $tenant->id)
                ->whereKey($childId)
                ->lockForUpdate()
                ->firstOrFail();

            Room::where('tenant_id', $tenant->id)
                ->whereKey($roomId)
                ->firstOrFail();

            $alreadyCheckedIn = CheckIn::where('tenant_id', $tenant->id)
                ->where('child_id', $child->id)
                ->whereNull('checked_out_at')
                ->exists();

            if ($alreadyCheckedIn) {
                throw ValidationException::withMessages([
                    'child_id' => 'This child is already checked into a room.',
                ]);
            }

            return CheckIn::create([
                'tenant_id' => $tenant->id,
                'child_id' => $child->id,
                'room_id' => $roomId,
                'checked_in_at' => now(),
            ]);
        });
    }
}