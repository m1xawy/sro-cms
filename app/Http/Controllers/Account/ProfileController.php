<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Setting;
use App\Models\SRO\Account\SecondaryPassword;
use App\Notifications\SendVerifyCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function index(Request $request): View
    {
        return view('account.index', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('account.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        if (config('global.account_verify')) {
            return $this->updateEmailByCode($request);
        }

        return $this->updateEmailByPassword($request);
    }

    protected function updateEmailByPassword(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        if ($user->isDirty('phone')) {
            $user->phone_verified_at = null;
        }

        $user->save();

        $user->updateGameEmail($request->email);

        return Redirect::route('account.edit')->with('status', 'profile-updated');
    }

    protected function updateEmailByCode(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->validate([
            'verify_code_email' => 'required|string',
            'new_email' => 'nullable|email|unique:users,email,'.$request->user()->id,
            'verify_login' => 'nullable|in:0,1',
        ]);

        $user = $request->user();
        $originalEmail = $user->email;
        $token = Cache::get('verify_code_'.$user->email);

        if (! $token || (int) $request->verify_code_email !== $token) {
            return back()->withErrors(['verify_code_email' => 'The provided verification code is invalid or expired.']);
        }

        if ($request->filled('new_email')) {
            $user->email = $request->new_email;
            $user->email_verified_at = null;
            $user->save();

            $user->updateGameEmail($request->new_email);
        }

        if ($request->has('phone') && $request->phone !== $user->phone) {
            $user->phone = $request->phone;
            $user->phone_verified_at = null;
            $user->save();
        }

        if ($request->has('verify_login')) {
            $verifyLogin = $request->input('verify_login');
            Setting::set("verify_jid_{$user->tbUser?->JID}", $verifyLogin);
        }

        Cache::forget('verify_code_'.$originalEmail);

        return Redirect::route('account.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        // $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    public function secondaryPasswordReset(Request $request): RedirectResponse
    {
        if (config('global.account_verify')) {
            return $this->resetSecondaryPasswordByCode($request);
        }

        return $this->secondaryPasswordResetByPassword($request);
    }

    public function secondaryPasswordResetByPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = $request->user();
        if (! $user->tbUser || ! hash_equals((string) $user->tbUser?->password, md5($request->password))) {
            return back()->with('passcode_error', 'Invalid password provided. Please try again.');
        }

        if (SecondaryPassword::where('UserJID', $user->tbUser?->JID)->delete()) {
            return back()->with('passcode_success', 'Your secondary password has been reset successfully!');
        }

        return back()->with('passcode_error', 'No secondary password was found for your account.');
    }

    private function resetSecondaryPasswordByCode(Request $request): RedirectResponse
    {
        $request->validate([
            'verify_code_secondary' => 'required|string',
        ]);

        $user = $request->user();
        $token = Cache::get('verify_code_'.$user->email);

        if (! $token || (int) $request->verify_code_secondary !== $token) {
            return back()->withErrors(['verify_code_secondary' => 'The provided verification code is invalid or expired.']);
        }

        Cache::forget('verify_code_'.$user->email);

        if (SecondaryPassword::where('UserJID', $user->tbUser?->JID)->delete()) {
            return back()->with('passcode_success', 'Your secondary password has been reset successfully!');
        }

        return back()->with('passcode_error', 'No secondary password was found for your account.');
    }

    public function sendVerifyCode(Request $request): RedirectResponse
    {
        $request->validate([
            'context' => 'required|string',
        ]);

        $user = $request->user();
        $code = random_int(100000, 999999);

        Cache::put('verify_code_'.$user->email, $code, now()->addMinutes(30));

        $user->notify(new SendVerifyCode($code));

        return back()->with('verify_code_sent', $request->input('context'));
    }

    public function updateSettings(Request $request)
    {
        $jid = auth()->user()->tbUser?->JID;

        $allowedKeys = [
            "item_stats_jid_{$jid}",
            "job_name_jid_{$jid}",
            "verify_jid_{$jid}",
        ];

        foreach ($request->except(['_token']) as $key => $value) {
            if (! in_array($key, $allowedKeys)) {
                continue;
            }

            Setting::updateOrCreate(['key' => $key], ['value' => is_array($value) ? json_encode($value) : $value]);
        }

        Setting::flushCache();

        return back()->with('success', 'Settings updated!');
    }
}
