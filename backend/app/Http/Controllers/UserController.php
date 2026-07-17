<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('roles')->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $users,
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
        ]);

        $user->syncRoles([$validated['role']]);

        return response()->json([
            'success' => true,
            'message' => 'User berhasil dibuat',
            'data' => $user->load('roles'),
        ], 201);
    }

    public function show(User $user)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $user->load('roles'),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $validated = $request->validated();

        $user->name = $validated['name'];
        $user->email = $validated['email'];

        if (! empty($validated['password'])) {
            $user->password = bcrypt($validated['password']);
        }

        $user->save();
        $user->syncRoles([$validated['role']]);

        return response()->json([
            'success' => true,
            'message' => 'User berhasil diperbarui',
            'data' => $user->load('roles'),
        ]);
    }

    public function destroy(User $user)
    {
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User berhasil dihapus',
            'data' => null,
        ]);
    }

    public function roles()
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => \Spatie\Permission\Models\Role::pluck('name'),
        ]);
    }
}