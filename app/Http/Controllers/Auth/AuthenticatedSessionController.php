<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Notifications\SendVerifyCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->validate([
            'username' => ['required'],
            'password' => ['required'],
            'g-recaptcha-response' => [Rule::requiredIf(function () {
                return config('captcha.enabled', false);
            }), 'captcha'],
        ]);

        $request->authenticate();

        $user = Auth::user();

        if ($this->requiresTwoFactor($user)) {
            Auth::guard('web')->logout();
            $request->session()->regenerate();

            return $this->startTwoFactorChallenge($request, $user);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('account', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }

    public function show(Request $request)
    {
        if (! $this->hasPendingChallenge($request)) {
            return redirect()->route('login');
        }

        return view('auth.verify-login');
    }

    private function requiresTwoFactor(User $user): bool
    {
        return (bool) config("global.verify_jid_{$user->tbUser?->JID}");
    }

    private function startTwoFactorChallenge(Request $request, User $user): RedirectResponse
    {
        $code = random_int(100000, 999999);

        Cache::put('verify_code_'.$user->email, $code, now()->addMinutes(30));

        $user->notify(new SendVerifyCode($code));

        $request->session()->put([
            'login_verify_user' => $user->id,
            'login_verify_time' => now()->timestamp,
        ]);

        return redirect()->route('login.show');
    }

    private function hasPendingChallenge(Request $request): bool
    {
        $userId = $request->session()->get('login_verify_user');
        $issuedAt = (int) $request->session()->get('login_verify_time');

        if (! $userId || ! $issuedAt) {
            return false;
        }

        if (now()->timestamp - $issuedAt > 1800) {
            $request->session()->forget(['login_verify_user', 'login_verify_time']);

            return false;
        }

        return true;
    }

    public function resendVerify(Request $request): RedirectResponse
    {
        if (! $this->hasPendingChallenge($request)) {
            return redirect()->route('login');
        }

        $user = User::find($request->session()->get('login_verify_user'));

        if (! $user) {
            $request->session()->forget(['login_verify_user', 'login_verify_time']);

            return redirect()->route('login');
        }

        $token = Cache::get('verify_code_'.$user->email);

        if ($token) {
            return back()->withErrors(['code' => 'Please wait before requesting a new code.']);
        }

        $code = random_int(100000, 999999);

        Cache::put('verify_code_'.$user->email, $code, now()->addMinutes(30));

        $user->notify(new SendVerifyCode($code));

        $request->session()->put('login_verify_time', now()->timestamp);

        return back()->with('status', 'Verification code sent again');
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        if (! $this->hasPendingChallenge($request)) {
            return redirect()->route('login');
        }

        $user = User::find($request->session()->get('login_verify_user'));

        if (! $user) {
            $request->session()->forget(['login_verify_user', 'login_verify_time']);

            return redirect()->route('login');
        }

        $throttleKey = 'login-verify:'.$user->id.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return back()->withErrors(['code' => 'Too many attempts. Please try again later.']);
        }

        $token = Cache::get('verify_code_'.$user->email);

        if (! $token || $token !== (int) $request->code) {
            RateLimiter::hit($throttleKey, 60);

            return back()->withErrors(['code' => 'Invalid or expired verification code']);
        }

        RateLimiter::clear($throttleKey);

        Cache::forget('verify_code_'.$user->email);

        Auth::login($user);

        $request->session()->regenerate();

        $request->session()->forget(['login_verify_user', 'login_verify_time']);

        return redirect()->intended(route('account', absolute: false));
    }
}
