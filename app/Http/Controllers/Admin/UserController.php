<?php

namespace App\Http\Controllers\Admin;

use App\Events\UserDeactivated;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index()
    {
        $users = User::select('id', 'name', 'email', 'additional_emails', 'role', 'is_active', 'created_at')
            ->orderBy('id')
            ->get();

        return Inertia::render("admin/UserManagement", [
            "users" => $users,
        ]);
    }

    public function createNewUser(Request $request)
    {
        $validated = $request->validate([
            "name" => ['required', 'max:255'],
            "email" => ['required', 'email', 'max:255'],
            "password" => ['required'],
            "role" => ['required'],
            'is_active' => ['nullable', 'boolean'],
            'additional_emails' => 'nullable|string',
        ]);

        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);

        User::create($validated);

        return redirect()->back();
    }

    public function updateUser(Request $request, User $user)
    {
        $validated = $request->validate([
            "name" => ['required', 'max:255'],
            "email" => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            "password" => ['nullable', 'min:6'], // optional when editing
            "role" => ['required'],
            'is_active' => ['nullable', 'boolean'],
            'additional_emails' => 'nullable|string',
        ]);

        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);

        /** @var User $actingUser */
        $actingUser = $request->user();

        $this->ensureAdminSafety($actingUser, $user, $validated['role'], $validated['is_active']);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']); // prevent overwriting with null
        }

        $wasActive = $user->is_active;

        $user->update($validated);

        if ($wasActive && ! $user->is_active) {
            $this->deactivateUser($user);
        }

        return redirect()->back();
    }

    public function deleteUser(User $user)
    {
        if ($user->role === 'admin' && $user->is_active) {
            $otherActiveAdminsExist = User::query()
                ->where('role', 'admin')
                ->where('is_active', true)
                ->whereKeyNot($user->id)
                ->exists();

            if (! $otherActiveAdminsExist) {
                throw ValidationException::withMessages([
                    'user' => 'You must keep at least one active admin account.',
                ]);
            }
        }

        $user->delete();

        return redirect()->back()->with('message', 'User deleted successfully.');
    }

    private function ensureAdminSafety(User $actingUser, User $targetUser, string $nextRole, bool $nextIsActive): void
    {
        if ($actingUser->is($targetUser) && ! $nextIsActive) {
            throw ValidationException::withMessages([
                'is_active' => 'You cannot deactivate your own account.',
            ]);
        }

        $isCurrentlyActiveAdmin = $targetUser->role === 'admin' && $targetUser->is_active;
        $willBeActiveAdmin = $nextRole === 'admin' && $nextIsActive;

        if (! $isCurrentlyActiveAdmin || $willBeActiveAdmin) {
            return;
        }

        $otherActiveAdminsExist = User::query()
            ->where('role', 'admin')
            ->where('is_active', true)
            ->whereKeyNot($targetUser->id)
            ->exists();

        if (! $otherActiveAdminsExist) {
            throw ValidationException::withMessages([
                'role' => 'You must keep at least one active admin account.',
            ]);
        }
    }

    private function deactivateUser(User $user): void
    {
        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->delete();

        $user->forceFill([
            'remember_token' => Str::random(60),
        ])->save();

        event(new UserDeactivated($user->fresh()));
    }
}
