<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Throwable;

class AuthController
{
    private const CUSTOM_BAR_VALUE = '__custom_bar__';

    public function loginForm(): View
    {
        return view('auth.login', [
            'lang' => app()->getLocale(),
        ]);
    }

    public function registerForm(): View
    {
        $lang = app()->getLocale();

        return view('auth.register', [
            'lang' => $lang,
            'courts' => $this->courts($lang),
            'customBarValue' => self::CUSTOM_BAR_VALUE,
        ]);
    }

    public function passwordForm(): View
    {
        return view('auth.password', [
            'lang' => app()->getLocale(),
        ]);
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'These credentials do not match our records.'])
                ->onlyInput('email');
        }

        $user = Auth::user();

        if ($user->access_status === 'suspended') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors(['email' => 'Your account has been suspended. Contact an administrator.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        $home = match ($user->role) {
            'admin' => route('admin.dashboard'),
            'advisor' => route('advisor.dashboard'),
            default => route('app.workspace'),
        };

        return redirect()->intended($home);
    }

    public function register(RegisterRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        unset($validated['password_confirmation']);

        $validated['bar'] = trim($validated['bar'] === self::CUSTOM_BAR_VALUE
            ? (string) $validated['custom_bar']
            : (string) $validated['bar']);
        unset($validated['custom_bar']);

        $user = User::create(array_merge($validated, [
            'access_status' => 'active',
        ]));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('app.workspace');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    private function courts(string $lang): array
    {
        $defaults = $this->defaultCourts($lang);

        try {
            $savedBars = User::query()
                ->whereNotNull('bar')
                ->where('bar', '!=', '')
                ->distinct()
                ->orderBy('bar')
                ->pluck('bar')
                ->map(fn ($bar) => trim((string) $bar))
                ->filter(fn (string $bar) => $bar !== '' && $bar !== self::CUSTOM_BAR_VALUE)
                ->all();
        } catch (Throwable) {
            $savedBars = [];
        }

        return array_values(array_unique([...$defaults, ...$savedBars]));
    }

    /**
     * @return list<string>
     */
    private function defaultCourts(string $lang): array
    {
        return match ($lang) {
            'ar' => [
                'هيئة القنيطرة / محكمة القنيطرة',
                'هيئة الدار البيضاء',
                'هيئة الرباط',
                'هيئة مراكش',
                'هيئة فاس',
                'هيئة طنجة',
                'هيئة أكادير',
            ],
            'fr' => [
                'Barreau de Kenitra / Tribunal de Kenitra',
                'Barreau de Casablanca',
                'Barreau de Rabat',
                'Barreau de Marrakech',
                'Barreau de Fes',
                'Barreau de Tanger',
                'Barreau d Agadir',
            ],
            default => [
                'Kenitra Bar / Kenitra Court',
                'Casablanca Bar',
                'Rabat Bar',
                'Marrakech Bar',
                'Fes Bar',
                'Tangier Bar',
                'Agadir Bar',
            ],
        };
    }
}
