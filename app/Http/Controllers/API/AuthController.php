<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\SRO\Account\TbUser;
use App\Models\SRO\Portal\AuhAgreedService;
use App\Models\SRO\Portal\MuEmail;
use App\Models\SRO\Portal\MuhAlteredInfo;
use App\Models\SRO\Portal\MuJoiningInfo;
use App\Models\SRO\Portal\MuUser;
use App\Models\SRO\Portal\MuVIPInfo;
use App\Models\User;
use App\Notifications\SendVerifyCode;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate($this->validationRules($request));

        $ip = filter_var($request->ip(), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ?: '0.0.0.0';

        try {
            DB::beginTransaction();

            $jid = config('global.server.version') === 'vSRO'
                ? $this->createVSROAccount($request, $ip)
                : $this->createISROAccount($request, $ip);

            $user = User::create([
                'jid' => $jid,
                'username' => $request->username,
                'email' => $request->email,
                'password' => $request->password,
            ]);

            $this->handleReferral($user, $request);

            DB::commit();

            if (config('global.register_confirm')) {
                event(new Registered($user));
            }

            $token = $user->createToken('api-token')->plainTextToken;

            return response()->json([
                'message' => 'Registration successful',
                'user' => $user,
                'token' => $token,
                'redirect' => url('/account'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            report($e);

            return response()->json([
                'message' => 'Registration failed',
            ], 500);
        }
    }

    private function validationRules(Request $request): array
    {
        $rules = [
            'username' => ['required', 'regex:/^[A-Za-z0-9]*$/', 'min:6', 'max:16', 'unique:'.User::class],
            'email' => ['required', 'email', 'max:70', 'unique:'.User::class],
            'password' => ['required', 'min:6', 'max:32', 'confirmed'],
            'g-recaptcha-response' => config('captcha.enabled', false) ? ['required', 'captcha'] : ['nullable'],
            'terms' => config('global.agree_terms', false) ? ['required', 'accepted'] : ['nullable'],
            'invite' => ['nullable', 'string'],
            'fingerprint' => ['nullable', 'string'],
        ];

        if (config('global.server.version') === 'vSRO') {
            $rules['username'][] = 'unique:'.TbUser::class.',StrUserID';
        } else {
            $rules['username'][] = 'unique:'.MuUser::class.',UserID';
            $rules['username'][] = 'unique:'.TbUser::class.',StrUserID';
            $rules['email'][] = 'unique:'.MuEmail::class.',EmailAddr';
        }

        return $rules;
    }

    private function createVSROAccount(Request $request, string $ip): int
    {
        return DB::transaction(function () use ($request, $ip) {
            $tbUser = TbUser::setVSROAccount(null, $request->username, $request->password, $request->email, $ip);
            TbUser::updateSilk($tbUser->JID, 3, 0);

            return $tbUser->JID;
        });
    }

    private function createISROAccount(Request $request, string $ip): int
    {
        return DB::transaction(function () use ($request, $ip) {
            $userBinIP = ip2long($ip);

            $portalUser = MuUser::setPortalAccount($request->username, $request->password);

            MuEmail::setEmail($portalUser->JID, $request->email);
            MuhAlteredInfo::setAlteredInfo($portalUser->JID, $request->username, $request->email, $userBinIP);
            AuhAgreedService::setAgreedService($portalUser->JID, $userBinIP);
            MuJoiningInfo::setJoiningInfo($portalUser->JID, $userBinIP);
            MuVIPInfo::setVIPInfo($portalUser->JID);

            // type 1 = silk, type 3 = premium silk
            // AphChangedSilk::setChangedSilk($portalUser->JID, 1, 0);
            // AphChangedSilk::setChangedSilk($portalUser->JID, 3, 0);

            TbUser::setISROAccount($portalUser->JID, $request->username, $request->password, $request->email, $ip);

            return $portalUser->JID;
        });
    }

    private function handleReferral(User $user, Request $request): void
    {
        if (! config('global.referral.enabled', true)) {
            return;
        }

        if ($request->filled('invite') && $request->filled('fingerprint')) {
            Referral::inviteReferral($user, $request->invite, $request->fingerprint, $request->ip());
        }
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'fingerprint' => ['nullable', 'string'],
        ]);

        $user = User::where('username', $request->username)->first();

        if ($user) {
            // A CMS account exists: only its own password may authenticate it.
            if (! Hash::check($request->password, $user->password)) {
                return response()->json([
                    'message' => 'Invalid credentials',
                ], 401);
            }
        } else {
            // Legacy migration path: create the CMS record only for game
            // accounts that do not have one yet.
            $tbUser = TbUser::where('StrUserID', $request->username)->where('password', md5($request->password))->first();

            if (! $tbUser) {
                return response()->json([
                    'message' => 'Invalid credentials',
                ], 401);
            }

            $jid = $tbUser->JID;
            $email = $tbUser->Email ?? "{$jid}@mail.com";

            $user = User::firstOrCreate(
                ['username' => $request->username],
                [
                    'jid' => $jid,
                    'email' => $email,
                    'password' => $request->password,
                ]
            );

            if (! $user->wasRecentlyCreated) {
                return response()->json([
                    'message' => 'Invalid credentials',
                ], 401);
            }
        }

        $result = $this->sendVerifyCode($user);

        if ($result) {
            $challenge = $user->createToken('api-verify-challenge', ['verify-code'], now()->addMinutes(30))->plainTextToken;

            return response()->json([
                'message' => 'Verify code sent',
                'verify_required' => true,
                'challenge_token' => $challenge,
            ], 403);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user,
        ]);
    }

    protected function sendVerifyCode(User $user): array
    {
        if (! config("global.verify_jid_{$user->tbUser?->JID}")) {
            return [];
        }

        $code = random_int(100000, 999999);

        Cache::put('verify_code_'.$user->email, $code, now()->addMinutes(30));

        $user->notify(new SendVerifyCode($code));

        return [
            'message' => 'Verify code sent',
            'verify_required' => true,
            'user' => $user,
        ];
    }

    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $user = $request->user();

        $throttleKey = 'api-login-verify:'.$user->id.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return response()->json(['message' => 'Too many attempts. Please try again later.'], 429);
        }

        $cached = Cache::get('verify_code_'.$user->email);

        if (! $cached || $cached !== (int) $request->code) {
            RateLimiter::hit($throttleKey, 60);

            return response()->json(['message' => 'Invalid or expired verification code'], 422);
        }

        RateLimiter::clear($throttleKey);

        Cache::forget('verify_code_'.$user->email);

        $current = $user->currentAccessToken();
        if ($current instanceof PersonalAccessToken) {
            $current->delete();
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Verification successful',
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function resendVerify(Request $request)
    {
        $user = $request->user();

        if (Cache::has('verify_code_'.$user->email)) {
            return response()->json(['message' => 'Please wait before requesting a new code.'], 429);
        }

        $code = random_int(100000, 999999);

        Cache::put('verify_code_'.$user->email, $code, now()->addMinutes(30));

        $user->notify(new SendVerifyCode($code));

        return response()->json(['message' => 'Verification code sent']);
    }

    public function forgot_password(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        Password::sendResetLink(
            $request->only('email')
        );

        // Deliberately identical response for known and unknown addresses to
        // avoid leaking which emails are registered.
        return response()->json([
            'message' => 'If that email is registered, a reset link has been sent.',
        ], 200);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully.',
        ]);
    }

    public function profile(Request $request)
    {
        $token = $request->user()->currentAccessToken();

        if ($token instanceof PersonalAccessToken && $token->cant('api-access')) {
            abort(403, 'Verification required.');
        }

        return response()->json($request->user());
    }
}
