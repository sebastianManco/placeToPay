<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class LoginController extends Controller
{
    /**
     * Display the login form.
     */
    public function index(): View
    {
        return view('configUsers.login');
    }
}
