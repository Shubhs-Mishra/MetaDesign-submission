<?php

namespace App\Services;

use App\Models\CheckIn;
use App\Models\Room;
use App\Models\Tenant;
use Illuminate\Support\Collection;

class RoomOccupantService
{
    public function getOccupants(Tenant $tenant, int $roomId): Collection
    {
        Room::where('tenant_id', $tenant->id)
            ->whereKey($roomId)
            ->firstOrFail();

        return CheckIn::where('tenant_id', $tenant->id)
            ->where('room_id', $roomId)
            ->whereNull('checked_out_at')
            ->with('child:id,name')
            ->get()
            ->pluck('child');
    }
}