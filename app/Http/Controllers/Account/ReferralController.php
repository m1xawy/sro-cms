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

        $fingerprint = $request->query('fingerprint');

        $invite = Referral::createReferral($user, $fingerprint);

        if ($fingerprint && is_null($invite->fingerprint)) {
            $invite->update(['fingerprint' => $fingerprint]);
            Cache::forget("user_invites_created_{$user->jid}");
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

    public function redeem(Request $request)
    {
        if (! config('global.referral.enabled', false)) {
            return back()->with('error', 'Redeemed invites disabled.');
        }

        $user = $request->user();
        $minimumRedeem = config('global.referral.minimum_redeem', 25);

        try {
            $redeemed = Cache::lock("referral-redeem:{$user->jid}", 15)->block(5, function () use ($user, $minimumRedeem) {
                return DB::transaction(function () use ($user, $minimumRedeem) {
                    $invites = $user->invitesCreated()->whereNotNull('invited_jid')->get();
                    $totalPoints = $invites->sum('points');

                    if ($totalPoints < $minimumRedeem) {
                        throw new \RuntimeException("You need at least {$minimumRedeem} points to redeem.");
                    }

                    TbUser::updateSilk($user->jid, config('global.referral.reward_type', 0), $totalPoints);

                    Donate::log([
                        'method' => 'Voucher',
                        'value' => $totalPoints,
                        'jid' => $user->jid,
                    ]);

                    $user->invitesCreated()->whereNotNull('invited_jid')->update(['points' => 0]);

                    return $totalPoints;
                });
            });
        } catch (LockTimeoutException $e) {
            return back()->with('error', 'A redemption is already in progress. Please try again.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$redeemed} Silk has been added to your account!");
    }
}
