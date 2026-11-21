<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Referral extends Model
{
    protected $fillable = [
        'code',
        'name',
        'ip',
        'fingerprint',
        'jid',
        'invited_jid',
        'points',
    ];

    public static function getReferralLogs(int $perPage = 20)
    {
        $logs = self::select('jid', DB::raw('SUM(points) as total_points'))
            ->groupBy('jid')
            ->orderByDesc('total_points')
            ->paginate($perPage);

        $jids = $logs->pluck('jid')->all();

        $latestReferrals = self::whereIn('jid', $jids)
            ->latest('created_at')
            ->get()
            ->load('creator')
            ->groupBy('jid');

        $logs->getCollection()->transform(function ($ref) use ($latestReferrals) {
            $referral = $latestReferrals[$ref->jid]->first();

            return (object)[
                'jid' => $ref->jid,
                'total_points' => $ref->total_points,
                'code' => $referral->code,
                'ip' => $referral->ip,
                'name' => optional($referral->creator)->username,
            ];
        });

        return $logs;
    }

    public static function createReferral(User $user, ?string $fingerprint = null, ?string $ip = null): self
    {
        $invite = $user->invitesCreated()->orderBy('id')->first();
        if ($invite) {
            return $invite;
        }

        do {
            $code = strtoupper(Str::random(8));
        } while (self::where('code', $code)->exists());

        $invite = $user->invitesCreated()->create([
            'code' => $code,
            'name' => $user->username,
            'jid' => $user->jid,
            'ip' => $ip ?? '0.0.0.0',
            'fingerprint' => $fingerprint,
        ]);

        $user->clearInvitesCache();

        return $invite;
    }

    public static function inviteReferral(User $user, string $inviteCode, ?string $fingerprint, string $ip): ?self
    {
        $inviteCode = Str::upper(trim($inviteCode));
        if ($inviteCode === '' || strlen($inviteCode) > 32) {
            return null;
        }

        $inviter = self::where('code', $inviteCode)->first();
        if (! $inviter) {
            return null;
        }

        if ((int) $inviter->jid === (int) $user->jid) {
            return null;
        }
        if (self::where('invited_jid', $user->jid)->exists()) {
            return null;
        }

        $sameOrigin = $inviter->ip !== '0.0.0.0' && $inviter->ip === $ip && $fingerprint !== null && $inviter->fingerprint === $fingerprint;
        $points = $sameOrigin ? 0 : (int) config('global.referral.reward_points', 0);

        return self::create([
            'code' => $inviter->code,
            'name' => $inviter->name,
            'jid' => $inviter->jid,
            'invited_jid' => $user->jid,
            'points' => $points,
            'ip' => $points === 0 ? null : $ip,
            'fingerprint' => $fingerprint,
        ]);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'jid', 'jid');
    }

    public function invitedUser()
    {
        return $this->belongsTo(User::class, 'invited_jid', 'jid');
    }
}
