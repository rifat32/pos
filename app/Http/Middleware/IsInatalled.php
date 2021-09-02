<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class IsInatalled
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $envPath = base_path('.env');
        if (!file_exists($envPath)) {
            return response()->json(["installed"=>false],403);
        }
        return $next($request);
    }
}
