<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\LoginApiRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuthApiController extends BaseApiController
{
    /**
     * Authenticate user credentials and return a new Bearer API Token.
     *
     * @param  \App\Http\Requests\Api\V1\LoginApiRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(LoginApiRequest $request): JsonResponse
    {
        $throttleKey = Str::transliterate(Str::lower($request->input('email')) . '|' . $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return $this->errorResponse(
                "Demasiados intentos de acceso fallidos. Por favor, intente nuevamente en {$seconds} segundos.",
                Response::HTTP_TOO_MANY_REQUESTS
            );
        }

        $user = User::where('email', $request->input('email'))->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            RateLimiter::hit($throttleKey);

            return $this->errorResponse(
                'Credenciales de acceso incorrectas.',
                Response::HTTP_UNAUTHORIZED
            );
        }

        if (! $user->isActive()) {
            return $this->errorResponse(
                'Tu cuenta se encuentra inactiva. Por favor, contacta al administrador.',
                Response::HTTP_FORBIDDEN
            );
        }

        RateLimiter::clear($throttleKey);

        // Calculate token abilities based on user role and permissions
        $abilities = $user->isAdmin()
            ? ['*']
            : $user->getCachedPermissionSlugs();

        if (empty($abilities)) {
            $abilities = ['basic'];
        }

        $deviceName = $request->input('device_name', 'api-client');
        $token = $user->createToken($deviceName, $abilities)->plainTextToken;

        return $this->successResponse([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'identification' => $user->identification,
                'name' => $user->name,
                'last_name' => $user->last_Name,
                'email' => $user->email,
                'phone' => $user->phone,
                'direction' => $user->direction,
                'is_active' => $user->isActive(),
                'role' => $user->role,
                'roles' => $user->roles->pluck('name', 'slug'),
                'permissions' => $abilities,
            ],
        ], 'Autenticación exitosa.');
    }

    /**
     * Revoke the current authenticated access token.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user instanceof User) {
            $user->currentAccessToken()->delete();
        }

        \Illuminate\Support\Facades\Auth::forgetGuards();

        return $this->successResponse(
            null,
            'Sesión cerrada y token revocado exitosamente.'
        );
    }

    /**
     * Retrieve the authenticated user profile and permissions.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function me(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $token = $user->currentAccessToken();
        $abilities = $token->abilities ?? [];

        $permissions = $user->isAdmin() ? ['*'] : $user->getCachedPermissionSlugs();

        return $this->successResponse([
            'id' => $user->id,
            'identification' => $user->identification,
            'name' => $user->name,
            'last_name' => $user->last_Name,
            'email' => $user->email,
            'phone' => $user->phone,
            'direction' => $user->direction,
            'is_active' => $user->isActive(),
            'role' => $user->role,
            'roles' => $user->roles->pluck('name', 'slug'),
            'permissions' => $permissions,
            'token_abilities' => $abilities,
        ], 'Información del usuario autenticado obtenida exitosamente.');
    }
}
