<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the dashboard view.
     */
    public function index(Request $request): View
    {
        return view('test', [
            'title' => $request->query('title', 'valor default'),
        ]);
    }
}
