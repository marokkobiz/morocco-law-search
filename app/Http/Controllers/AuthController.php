<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function loginForm(Request $request): View
    {
        return view('auth-page', [
            'mode' => 'login',
            'lang' => $this->language($request),
        ]);
    }

    public function registerForm(Request $request): View
    {
        return view('auth-page', [
            'mode' => 'register',
            'lang' => $this->language($request),
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'These credentials do not match our records.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended('/app?lang='.$this->language($request));
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'bar' => ['required', 'string', 'max:255'],
            'password' => ['required', Password::min(8)],
        ]);

        $user = User::query()->create($validated + [
            'access_status' => config('billing.require_payment') ? 'pending_payment' : 'active',
            'trial_ends_at' => config('billing.default_trial_days') > 0
                ? now()->addDays((int) config('billing.default_trial_days'))
                : null,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        if ($user->requiresPayment()) {
            return redirect('/billing?lang='.$this->language($request));
        }

        return redirect('/app?lang='.$this->language($request));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login?lang='.$this->language($request));
    }

    private function language(Request $request): string
    {
        $lang = (string) $request->input('lang', $request->query('lang', 'en'));

        return in_array($lang, ['en', 'fr', 'ar'], true) ? $lang : 'en';
    }
}
