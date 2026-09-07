<?php

namespace App\Http\Controllers;
use App\Http\Requests\registerUserRequest;
use Illuminate\Http\Request;
use App\Models\User;

class registerUserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
      
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {

        return view('configUsers.registered');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\registerUserRequest  $request
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function store(registerUserRequest $request)
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

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
