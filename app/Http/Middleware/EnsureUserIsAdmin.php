<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || (! $request->user()->isAdmin() && ! $request->user()->hasAnyAdminPermission())) {
            abort(403, 'Acceso denegado. Solo usuarios con permisos administrativos pueden ingresar a este panel.');
        }

        return $next($request);
    }
}
