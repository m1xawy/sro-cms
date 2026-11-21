<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\SRO\Account\TbUser;
use App\Models\SRO\Portal\AphChangedSilk;
use App\Models\SRO\Portal\AuhAgreedService;
use App\Models\SRO\Portal\MuEmail;
use App\Models\SRO\Portal\MuhAlteredInfo;
use App\Models\SRO\Portal\MuJoiningInfo;
use App\Models\SRO\Portal\MuUser;
use App\Models\SRO\Portal\MuVIPInfo;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        if (config('global.disable_register')) {
            return back()->withErrors(['username' => ['Register page is disabled!']]);
        }

        $request->validate($this->getValidationRules($request));

        try {
            DB::beginTransaction();

            if (config('global.server.version') === 'vSRO') {
                $jid = $this->createAccountVSRO($request);
            } else {
                $jid = $this->createAccountISRO($request);
            }

            $user = User::create([
                'jid' => $jid,
                'username' => $request->username,
                'email' => $request->email,
                'phone' => config('services.whatsapp.enabled', false) ? $request->phone : null,
                'password' => $request->password,
            ]);

            $this->handleReferral($user, $request);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors(['error' => 'Registration failed. Please try again.']);
        }

        Auth::login($user);

        $this->handleRegistrationMessages($user, $request);

        return redirect(route('account', absolute: false));
    }

    private function getValidationRules(Request $request): array
    {
        $rules = [
            'username' => ['required', 'regex:/^[A-Za-z0-9]*$/', 'min:6', 'max:16', 'unique:'.User::class],
            'email' => ['required', 'string', 'email', 'max:70', 'unique:'.User::class],
            'phone' => config('services.whatsapp.enabled', false) ? ['required', 'string', 'max:32', 'regex:/^\+?[0-9]{7,15}$/'] : ['nullable'],

            'password' => ['required', 'min:6', 'max:32', 'confirmed'],
            'g-recaptcha-response' => config('captcha.enabled', false) ? ['required', 'captcha'] : ['nullable'],
            'terms' => config('global.agree_terms', false) ? ['required', 'accepted'] : ['nullable'],
            'invite' => ['nullable', 'string', 'max:32'],
            'fingerprint' => ['nullable', 'string', 'max:64'],
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

    private function createAccountVSRO(Request $request): int
    {
        return DB::transaction(function () use ($request) {
            $ip = filter_var($request->ip(), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ?: '0.0.0.0';

            $tbUser = TbUser::setVSROAccount(null, $request->username, $request->password, $request->email, $ip);
            TbUser::updateSilk($tbUser->JID, 0, 0);

            return $tbUser->JID;
        });
    }

    private function createAccountISRO(Request $request): int
    {
        return DB::transaction(function () use ($request) {
            $ip = filter_var($request->ip(), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ?: '0.0.0.0';

            $portalUser = MuUser::setPortalAccount($request->username, $request->password);

            MuEmail::setEmail($portalUser->JID, $request->email);
            MuhAlteredInfo::setAlteredInfo($portalUser->JID, $request->username, $request->email, ip2long($ip));
            AuhAgreedService::setAgreedService($portalUser->JID, ip2long($ip));
            MuJoiningInfo::setJoiningInfo($portalUser->JID, ip2long($ip));
            MuVIPInfo::setVIPInfo($portalUser->JID);
            AphChangedSilk::setChangedSilk($portalUser->JID, 3, 0);
            TbUser::setISROAccount($portalUser->JID, $request->username, $request->password, $request->email, $ip);

            return $portalUser->JID;
        });
    }

    private function handleRegistrationMessages(User $user, Request $request): void
    {
        $whatsapp = app(WhatsAppService::class);
        $hasPhone = $request->filled('phone');

        if (config('services.whatsapp.welcome_enabled', true) && $hasPhone && $whatsapp->enabled()) {
            $whatsapp->sendText(
                $request->phone,
                __((string) config(
                    'services.whatsapp.welcome_message',
                    'Welcome to :site, :username! Your account has been created successfully.'
                ), ['site' => config('global.site_name'), 'username' => $user->username])
            );
        }

        if (! config('global.register_confirm')) {
            return;
        }

        if ($hasPhone && $whatsapp->enabled() && config('services.whatsapp.confirm_enabled', false)) {
            $whatsapp->sendVerificationLink($user);

            return;
        }

        event(new Registered($user));
    }

    private function handleReferral(User $user, Request $request): void
    {
        if (! config('global.referral.enabled', false)) {
            return;
        }

        parse_str((string) parse_url((string) $request->headers->get('referer'), PHP_URL_QUERY), $query);
        $invite = $request->input('invite') ?? ($query['invite'] ?? null);

        Referral::createReferral($user, $request->input('fingerprint'), $request->ip());

        if (is_string($invite)) {
            Referral::inviteReferral($user, $invite, $request->input('fingerprint'), $request->ip());
        }
    }
}
