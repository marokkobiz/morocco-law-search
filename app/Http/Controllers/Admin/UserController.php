<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class UserController
{
    public function index(): View
    {
        return view('admin.users', [
            'users' => User::latest()->get(),
        ]);
    }

    public function toggleAgent(User $user): RedirectResponse
    {
        // Safety check to ensure we never modify an admin's role
        if ($user->role === 'admin') {
            return back()->with('error', 'Cannot change the role of an administrator.');
        }

        // Toggle role between user and agent
        $newRole = $user->role === 'agent' ? 'user' : 'agent';
        
        $user->forceFill([
            'role' => $newRole
        ])->save();

        return back();
    }
}