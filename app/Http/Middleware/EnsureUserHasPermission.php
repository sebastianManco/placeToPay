<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$permissions
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Acceso denegado. Se requiere inicio de sesión.');
        }

        // Flatten comma or pipe separated permissions
        $parsedPermissions = [];
        foreach ($permissions as $permission) {
            foreach (preg_split('/[,|]/', $permission) as $item) {
                $trimmed = trim($item);
                if ($trimmed !== '') {
                    $parsedPermissions[] = $trimmed;
                }
            }
        }

        if (empty($parsedPermissions)) {
            return $next($request);
        }

        if (! $user->hasAnyPermission($parsedPermissions)) {
            abort(403, 'Acceso denegado. No cuentas con los permisos granulares necesarios para esta funcionalidad.');
        }

        return $next($request);
    }
}
