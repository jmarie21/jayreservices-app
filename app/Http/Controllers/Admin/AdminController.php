<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class AdminController extends Controller
{
    public function index()
    {
        $users = User::select('id', 'name', 'email', 'role', 'created_at')
            ->orderBy('id')
            ->get();

        return Inertia::render("admin/UserManagement", [
            "users" => $users
        ]);
    }

    public function createNewUser(Request $request)
    {
        $validated = $request->validate([
            "name" => ['required', 'max:255'],
            "email" => ['required', 'email', 'max:255'],
            "password" => ['required'],
            "role" => ['required']
        ]);

        User::create($validated);

        return redirect()->back();
    }

     public function updateUser(Request $request, User $user)
    {
        $validated = $request->validate([
            "name" => ['required', 'max:255'],
            "email" => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            "password" => ['nullable', 'min:6'], // optional when editing
            "role" => ['required']
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']); // prevent overwriting with null
        }

        $user->update($validated);

        return redirect()->back();
    }

    public function deleteUser(User $user)
    {
        $user->delete();

        return redirect()->back()->with('message', 'User deleted successfully.');
    }
}
