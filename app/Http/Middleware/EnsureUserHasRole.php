<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Acceso denegado. Se requiere inicio de sesión.');
        }

        $parsedRoles = [];
        foreach ($roles as $role) {
            foreach (preg_split('/[,|]/', $role) as $item) {
                $trimmed = trim($item);
                if ($trimmed !== '') {
                    $parsedRoles[] = $trimmed;
                }
            }
        }

        if (empty($parsedRoles)) {
            return $next($request);
        }

        if (! $user->hasAnyRole($parsedRoles)) {
            abort(403, 'Acceso denegado. No tienes el rol requerido para esta funcionalidad.');
        }

        return $next($request);
    }
}
