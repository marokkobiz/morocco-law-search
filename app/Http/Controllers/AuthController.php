<?php

namespace App\Http\Controllers;

use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class AuthController extends Controller
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
            'referralCode' => request('ref'),
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

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'These credentials do not match our records.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();
        if (! Auth::user()->hasVerifiedEmail()) {
            Auth::logout();

            return redirect()
                ->route('verification.notice')
                ->with('message', 'Please verify your email first.');
        }
 
        return redirect()->intended('/dashboard');
    }

    public function register(RegisterRequest $request): RedirectResponse
    {
        $validated = $request->validated();

$referralCode = $validated['referral_code'] ?? null;

unset(
    $validated['password_confirmation'],
    $validated['referral_code']
);

        $validated['bar'] = trim($validated['bar'] === self::CUSTOM_BAR_VALUE
            ? (string) $validated['custom_bar']
            : (string) $validated['bar']);
        unset($validated['custom_bar']);

        $agent = null;

if ($referralCode) {
    $agent = User::where('referral_code', $referralCode)->first();
}

$user = User::create(array_merge($validated, [

    'referral_code' => $this->generateReferralCode(),

    'referred_by' => $agent?->id,

    'access_status' => config('billing.require_payment')
        ? 'pending_payment'
        : 'active',

    'trial_ends_at' => config('billing.default_trial_days') > 0
        ? now()->addDays(config('billing.default_trial_days'))
        : null,
]));

        Auth::login($user);
        $request->session()->regenerate();

        $user->sendEmailVerificationNotification();

        return redirect()->route('verification.notice');
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

private function generateReferralCode(): string
{
    do {
        $code = strtoupper(Str::random(8));
    } while (
        User::where('referral_code', $code)->exists()
    );

    return $code;
}

}
