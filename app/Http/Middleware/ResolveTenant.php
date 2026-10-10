<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $request->header('X-Tenant-ID');

        if (!$tenantId || !ctype_digit($tenantId)) {
            return response()->json([
                'message' => 'A valid X-Tenant-ID header is required.',
            ], 400);
        }

        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return response()->json([
                'message' => 'Tenant not found.',
            ], 404);
        }

        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }
}