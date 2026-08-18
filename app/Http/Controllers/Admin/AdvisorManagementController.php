<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AdvisorCredentialsMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdvisorManagementController extends Controller
{
    public function index(): View
    {
        return view('admin.advisors.index', [
            'advisors' => User::where('role', 'advisor')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.advisors.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        $temporaryPassword = Str::password(14);

        $advisor = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? '',
            'password' => $temporaryPassword,
            'role' => 'advisor',
            'access_status' => 'active',
            'company' => 'MarocLoi',
            'bar' => '',
        ]);

        Mail::to($advisor->email)
            ->locale('en')
            ->queue(new AdvisorCredentialsMail($advisor, $temporaryPassword));

        return redirect()
            ->route('admin.advisors.index')
            ->with('success', 'Advisor '.$advisor->name.' created — credentials sent to '.$advisor->email.'.');
    }

    public function edit(User $advisor): View
    {
        abort_unless($advisor->isAdvisor(), 404);

        return view('admin.advisors.edit', [
            'advisor' => $advisor,
        ]);
    }

    public function update(Request $request, User $advisor): RedirectResponse
    {
        abort_unless($advisor->isAdvisor(), 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$advisor->id],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        $advisor->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? '',
        ]);

        return back()->with('success', 'Advisor '.$advisor->name.' updated.');
    }

    public function suspend(User $advisor): RedirectResponse
    {
        abort_unless($advisor->isAdvisor(), 404);

        if ($advisor->id === auth()->id()) {
            return back()->with('error', 'You cannot suspend your own account.');
        }

        $advisor->forceFill(['access_status' => 'suspended'])->save();

        return back()->with('success', 'Advisor '.$advisor->name.' has been suspended.');
    }

    public function unsuspend(User $advisor): RedirectResponse
    {
        abort_unless($advisor->isAdvisor(), 404);

        $advisor->forceFill(['access_status' => 'active'])->save();

        return back()->with('success', 'Advisor '.$advisor->name.' has been reactivated.');
    }

    public function resetPassword(User $advisor): RedirectResponse
    {
        abort_unless($advisor->isAdvisor(), 404);

        $temporaryPassword = Str::password(14);

        $advisor->update([
            'password' => $temporaryPassword,
        ]);

        Mail::to($advisor->email)
            ->locale('en')
            ->queue(new AdvisorCredentialsMail($advisor, $temporaryPassword));

        return back()->with('success', 'New credentials sent to '.$advisor->email.'.');
    }

    public function destroy(User $advisor): RedirectResponse
    {
        abort_unless($advisor->isAdvisor(), 404);

        if ($advisor->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $advisor->delete();

        return redirect()
            ->route('admin.advisors.index')
            ->with('success', 'Advisor deleted.');
    }
}