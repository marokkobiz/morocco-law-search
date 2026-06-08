<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

class AuthController extends Controller
{
    private const CUSTOM_BAR_VALUE = '__custom_bar__';

    public function loginForm(Request $request): View
    {
        return view('auth-page', [
            'mode' => 'login',
            'lang' => $this->language($request),
        ]);
    }

    public function registerForm(Request $request): View
    {
        $lang = $this->language($request);

        return view('auth-page', [
            'mode' => 'register',
            'lang' => $lang,
            'courts' => $this->courts($lang),
            'customBarValue' => self::CUSTOM_BAR_VALUE,
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
            'custom_bar' => ['nullable', 'required_if:bar,'.self::CUSTOM_BAR_VALUE, 'string', 'max:255'],
            'password' => ['required', Password::min(8)],
        ]);

        $validated['bar'] = trim($validated['bar'] === self::CUSTOM_BAR_VALUE
            ? (string) $validated['custom_bar']
            : (string) $validated['bar']);
        unset($validated['custom_bar']);

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

    /**
     * Default bars stay visible, and saved custom bars become available to the next users.
     *
     * @return list<string>
     */
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
