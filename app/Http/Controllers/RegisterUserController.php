<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RegisterUserController extends Controller
{
    /**
     * Show the form for creating a new user.
     */
    public function create(): View
    {
        return view('configUsers.registered');
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(RegisterUserRequest $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'identification' => $validated['identification'],
            'name' => $validated['name'],
            'last_Name' => $validated['lastName'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'direction' => $validated['direction'],
            'user_Name' => $validated['userName'],
            'password' => $validated['password'],
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'User registered successfully.',
                'user' => $user,
            ], 201);
        }

        return redirect()->back()->with('success', 'User registered successfully.');
    }
}

