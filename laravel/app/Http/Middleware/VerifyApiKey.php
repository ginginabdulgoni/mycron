<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\ApiKey;

class VerifyApiKey
{
    public function handle(Request $request, Closure $next)
    {
        $key = $request->header('X-API-KEY');

        if (!$key || !ApiKey::where('key', $key)->where('active', true)->exists()) {
            return response()->json(['message' => 'Unauthorized API key'], 401);
        }

        return $next($request);
    }
}
