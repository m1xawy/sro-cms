<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Donate;
use App\Models\Referral;
use App\Models\SRO\Account\TbUser;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ReferralController extends Controller
{
    public function index(Request $request)
    {
        abort_if(! config('global.referral.enabled', false), 403);
        $user = $request->user();

        $invite = Referral::createReferral($user, null, $request->ip());
        if (($invite->ip === null || $invite->ip === '0.0.0.0') && $request->ip()) {
            $invite->update(['ip' => $request->ip()]);
            $user->clearInvitesCache();
        }

        $usedInvites = $user->getInvitesCreated()->whereNotNull('invited_jid')->load('invitedUser');
        $totalPoints = $usedInvites->sum('points');

        $minimumRedeem = config('global.referral.minimum_redeem', 25);

        return view('account.referral.index', [
            'invite' => $invite,
            'usedInvites' => $usedInvites,
            'totalPoints' => $totalPoints,
            'minimumRedeem' => $minimumRedeem,
        ]);
    }

    public function storeFingerprint(Request $request)
    {
        if (! config('global.referral.enabled', false)) {
            return response()->json(['ok' => false], 403);
        }

        $validated = $request->validate([
            'fingerprint' => ['required', 'string', 'max:64'],
        ]);

        $user = $request->user();
        $invite = Referral::createReferral($user, $validated['fingerprint'], $request->ip());

        // Set once. A later visit must not overwrite the stored value.
        if (is_null($invite->fingerprint)) {
            $invite->update(['fingerprint' => $validated['fingerprint']]);
            $user->clearInvitesCache();
        }

        return response()->json(['ok' => true]);
    }

    public function redeem(Request $request)
    {
        if (! config('global.referral.enabled', false)) {
            return back()->with('error', 'Redeemed invites disabled.');
        }

        $user = $request->user();
        $minimumRedeem = (int) config('global.referral.minimum_redeem', 25);
        $rewardType = (int) config('global.referral.reward_type', 0);

        try {
            $redeemed = Cache::lock("referral-redeem:{$user->jid}", 15)->block(5, function () use ($user, $minimumRedeem, $rewardType) {
                return DB::transaction(function () use ($user, $minimumRedeem, $rewardType) {
                    $invites = $user->invitesCreated()->whereNotNull('invited_jid')->lockForUpdate()->get();
                    $totalPoints = (int) $invites->sum('points');

                    if ($totalPoints < $minimumRedeem) {
                        throw new \RuntimeException("You need at least {$minimumRedeem} points to redeem.");
                    }

                    $user->invitesCreated()->whereNotNull('invited_jid')->update(['points' => 0]);

                    Donate::log([
                        'method' => 'Voucher',
                        'value' => $totalPoints,
                        'jid' => $user->jid,
                    ]);

                    TbUser::updateSilk($user->jid, $rewardType, $totalPoints);

                    return $totalPoints;
                });
            });
        } catch (LockTimeoutException $e) {
            return back()->with('error', 'A redemption is already in progress. Please try again.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $user->clearInvitesCache();

        return back()->with('success', "{$redeemed} Silk has been added to your account!");
    }
}
