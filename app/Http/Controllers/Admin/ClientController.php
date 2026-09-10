<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientController extends Controller
{
    /**
     * Display a listing of clients.
     */
    public function index(Request $request): View
    {
        $search = $request->query('search');
        $status = $request->query('status');

        $query = User::query()->where('role', 'client');

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('identification', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $clients = $query->orderBy('name')->paginate(10)->withQueryString();

        return view('admin.clients.index', compact('clients', 'search', 'status'));
    }

    /**
     * Show the form for editing the specified client.
     */
    public function edit(User $client): View
    {
        abort_if($client->role !== 'client', 404);

        $client->load('roles');
        $roles = Role::orderBy('name')->get();
        $clientRoles = $client->roles->pluck('id')->toArray();

        return view('admin.clients.edit', compact('client', 'roles', 'clientRoles'));
    }

    /**
     * Update the specified client in storage.
     */
    public function update(UpdateClientRequest $request, User $client): RedirectResponse
    {
        abort_if($client->role !== 'client', 404);

        $validated = $request->validated();

        $client->update([
            'name' => $validated['name'],
            'last_name' => $validated['last_name'] ?? $validated['last_Name'] ?? $client->last_name,
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'direction' => $validated['direction'],
            'is_active' => $request->has('is_active') ? (bool) $request->input('is_active') : $client->is_active,
        ]);

        if ($request->has('roles')) {
            $client->syncRoles($request->input('roles', []));
        }

        return redirect()->route('admin.clients.index')
            ->with('success', "El cliente {$client->name} {$client->last_name} ha sido actualizado correctamente.");
    }

    /**
     * Toggle client active status.
     */
    public function toggleStatus(User $user): RedirectResponse
    {
        $user->is_active = ! $user->is_active;
        $user->save();

        $statusText = $user->is_active ? 'activado' : 'desactivado';

        return redirect()->back()->with('success', "El cliente {$user->name} ha sido {$statusText} correctamente.");
    }
}
