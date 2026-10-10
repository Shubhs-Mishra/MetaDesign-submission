<?php

namespace App\Services;

use App\Models\CheckIn;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckOutService
{
    public function checkOut(Tenant $tenant, int $checkInId): CheckIn
    {
        return DB::transaction(function () use ($tenant, $checkInId) {
            $checkIn = CheckIn::where('tenant_id', $tenant->id)
                ->whereKey($checkInId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($checkIn->checked_out_at !== null) {
                throw ValidationException::withMessages([
                    'check_in' => 'This child has already been checked out.',
                ]);
            }

            $checkIn->update(['checked_out_at' => now()]);

            return $checkIn->refresh();
        });
    }
}