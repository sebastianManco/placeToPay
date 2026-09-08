<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
                  ->orWhere('last_Name', 'like', "%{$search}%")
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
