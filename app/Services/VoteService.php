<?php

namespace App\Services;

use App\Models\Donate;
use App\Models\Error;
use App\Models\SRO\Account\TbUser;
use App\Models\User;
use App\Models\Vote;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VoteService
{
    public function postbackXtremetop100(Request $request)
    {
        $config = config('vote.xtremetop100');
        // Only the TrustProxies-resolved IP; CF-Connecting-IP is client-spoofable.
        $remoteIp = $request->ip() ?? '0.0.0.0';

        $allowedIps = array_map('trim', explode(',', $config['ip']));
        if (! in_array($remoteIp, $allowedIps, true)) {
            Error::log(['subject' => $config['route'].' postback', 'message' => 'Unauthorized IP: '.$remoteIp.' | Allowed IPs: '.$config['ip'], 'ip' => $remoteIp]);

            return response("Unauthorized IP: {$remoteIp}", 401);
        }

        $jid = $request->input('custom');
        if (! $jid) {
            Error::log(['subject' => $config['route'].' postback', 'message' => 'Missing user ID | IP: '.$remoteIp, 'ip' => $remoteIp]);

            return response('Missing user ID', 400);
        }

        $user = User::where('jid', (int) $jid)->first();
        if (! $user) {
            Error::log(['subject' => $config['route'].' postback', 'message' => 'User not found. User ID: '.$jid.' | IP: '.$remoteIp, 'jid' => (int) $jid, 'ip' => $remoteIp]);

            return response('User not found', 200);
        }

        // Cooldown row locked + re-checked in-transaction: concurrent postbacks
        // serialize and only one grants the reward per timeout window.
        $timeout = $config['timeout'] ?? 12;
        $rewardAmount = $config['reward'] ?? 0;

        try {
            $outcome = DB::transaction(function () use ($user, $rewardAmount, $config, $jid, $remoteIp, $timeout) {
                $now = Carbon::now();

                $voteLog = Vote::where('jid', $jid)->where('site', $config['route'])->lockForUpdate()->first();
                if ($voteLog && $voteLog->expire && $now->lessThan(Carbon::parse($voteLog->expire))) {
                    return 'cooldown:'.$voteLog->expire;
                }

                TbUser::updateSilk($user->jid, $config['type'] ?? 0, $rewardAmount);

                Donate::log([
                    'method' => "Vote [{$config['name']}]",
                    'value' => $rewardAmount,
                    'jid' => $user->jid,
                ]);

                Vote::updateOrCreate(['jid' => $jid, 'site' => $config['route']], ['ip' => $remoteIp, 'expire' => $now->addHours($timeout)]);

                return 'ok';
            });
        } catch (\Throwable $e) {
            Error::log(['subject' => $config['route'].' postback', 'message' => 'Failed to complete vote: '.$e->getMessage().' | User ID: '.$user->jid.' | Site: '.$config['route'].' | Reward: '.$rewardAmount.' | IP: '.$remoteIp, 'jid' => $user->jid, 'ip' => $remoteIp]);

            throw $e;
        }

        if (str_starts_with($outcome, 'cooldown:')) {
            return response('Cooldown active until '.substr($outcome, 9), 200);
        }

        return response('OK', 200);
    }

    public function postbackGtop100(Request $request)
    {
        $config = config('vote.gtop100');
        // Only the TrustProxies-resolved IP; CF-Connecting-IP is client-spoofable.
        $remoteIp = $request->ip() ?? '0.0.0.0';

        $allowedIps = array_map('trim', explode(',', $config['ip']));
        if (! in_array($remoteIp, $allowedIps, true)) {
            Error::log(['subject' => $config['route'].' postback', 'message' => 'Unauthorized IP: '.$remoteIp.' | Allowed IPs: '.$config['ip'], 'ip' => $remoteIp]);

            return response("Unauthorized IP: {$remoteIp}", 401);
        }

        $jid = $request->input('pingUsername');
        if (! $jid) {
            Error::log(['subject' => $config['route'].' postback', 'message' => 'Missing user ID | IP: '.$remoteIp, 'ip' => $remoteIp]);

            return response('Missing user ID', 400);
        }

        if ((int) $request->input('Successful') !== 1) {
            Error::log(['subject' => $config['route'].' postback', 'message' => 'Vote not successful. Reason: '.($request->input('Reason') ?? 'unknown').' | Successful: '.$request->input('Successful').' | User ID: '.$jid.' | IP: '.$remoteIp, 'jid' => (int) $jid, 'ip' => $remoteIp]);

            return response($request->input('Reason') ?? 'Vote not successful', 200);
        }

        $user = User::where('jid', (int) $jid)->first();
        if (! $user) {
            Error::log(['subject' => $config['route'].' postback', 'message' => 'User not found. User ID: '.$jid.' | IP: '.$remoteIp, 'jid' => (int) $jid, 'ip' => $remoteIp]);

            return response('User not found', 200);
        }

        // Cooldown row locked + re-checked in-transaction: concurrent postbacks
        // serialize and only one grants the reward per timeout window.
        $timeout = $config['timeout'] ?? 12;
        $rewardAmount = $config['reward'] ?? 0;

        try {
            $outcome = DB::transaction(function () use ($user, $rewardAmount, $config, $jid, $remoteIp, $timeout) {
                $now = Carbon::now();

                $voteLog = Vote::where('jid', $jid)->where('site', $config['route'])->lockForUpdate()->first();
                if ($voteLog && $voteLog->expire && $now->lessThan(Carbon::parse($voteLog->expire))) {
                    return 'cooldown:'.$voteLog->expire;
                }

                TbUser::updateSilk($user->jid, $config['type'] ?? 0, $rewardAmount);

                Donate::log([
                    'method' => "Vote [{$config['name']}]",
                    'value' => $rewardAmount,
                    'jid' => $user->jid,
                ]);

                Vote::updateOrCreate(['jid' => $jid, 'site' => $config['route']], ['ip' => $remoteIp, 'expire' => $now->addHours($timeout)]);

                return 'ok';
            });
        } catch (\Throwable $e) {
            Error::log(['subject' => $config['route'].' postback', 'message' => 'Failed to complete vote: '.$e->getMessage().' | User ID: '.$user->jid.' | Site: '.$config['route'].' | Reward: '.$rewardAmount.' | IP: '.$remoteIp, 'jid' => $user->jid, 'ip' => $remoteIp]);

            throw $e;
        }

        if (str_starts_with($outcome, 'cooldown:')) {
            return response('Cooldown active until '.substr($outcome, 9), 200);
        }

        return response('OK', 200);
    }

    public function postbackTopg(Request $request)
    {
        $config = config('vote.topg');
        // Only the TrustProxies-resolved IP; CF-Connecting-IP is client-spoofable.
        $remoteIp = $request->ip() ?? '0.0.0.0';

        $allowedIps = array_map('trim', explode(',', $config['ip']));
        if (! in_array($remoteIp, $allowedIps, true)) {
            Error::log(['subject' => $config['route'].' postback', 'message' => 'Unauthorized IP: '.$remoteIp.' | Allowed IPs: '.$config['ip'], 'ip' => $remoteIp]);

            return response("Unauthorized IP: {$remoteIp}", 401);
        }

        $jid = $request->input('p_resp');
        if (! $jid) {
            Error::log(['subject' => $config['route'].' postback', 'message' => 'Missing user ID | IP: '.$remoteIp, 'ip' => $remoteIp]);

            return response('Missing user ID', 400);
        }

        $user = User::where('jid', (int) $jid)->first();
        if (! $user) {
            Error::log(['subject' => $config['route'].' postback', 'message' => 'User not found. User ID: '.$jid.' | IP: '.$remoteIp, 'jid' => (int) $jid, 'ip' => $remoteIp]);

            return response('User not found', 200);
        }

        // Cooldown row locked + re-checked in-transaction: concurrent postbacks
        // serialize and only one grants the reward per timeout window.
        $timeout = $config['timeout'] ?? 12;
        $rewardAmount = $config['reward'] ?? 0;

        try {
            $outcome = DB::transaction(function () use ($user, $rewardAmount, $config, $jid, $remoteIp, $timeout) {
                $now = Carbon::now();

                $voteLog = Vote::where('jid', $jid)->where('site', $config['route'])->lockForUpdate()->first();
                if ($voteLog && $voteLog->expire && $now->lessThan(Carbon::parse($voteLog->expire))) {
                    return 'cooldown:'.$voteLog->expire;
                }

                TbUser::updateSilk($user->jid, $config['type'] ?? 0, $rewardAmount);

                Donate::log([
                    'method' => "Vote [{$config['name']}]",
                    'value' => $rewardAmount,
                    'jid' => $user->jid,
                ]);

                Vote::updateOrCreate(['jid' => $jid, 'site' => $config['route']], ['ip' => $remoteIp, 'expire' => $now->addHours($timeout)]);

                return 'ok';
            });
        } catch (\Throwable $e) {
            Error::log(['subject' => $config['route'].' postback', 'message' => 'Failed to complete vote: '.$e->getMessage().' | User ID: '.$user->jid.' | Site: '.$config['route'].' | Reward: '.$rewardAmount.' | IP: '.$remoteIp, 'jid' => $user->jid, 'ip' => $remoteIp]);

            throw $e;
        }

        if (str_starts_with($outcome, 'cooldown:')) {
            return response('Cooldown active until '.substr($outcome, 9), 200);
        }

        return response('OK', 200);
    }

    public function postbackTop100arena(Request $request)
    {
        $config = config('vote.top100arena');
        // Only the TrustProxies-resolved IP; CF-Connecting-IP is client-spoofable.
        $remoteIp = $request->ip() ?? '0.0.0.0';

        $allowedIps = array_map('trim', explode(',', $config['ip']));
        if (! in_array($remoteIp, $allowedIps, true)) {
            Error::log(['subject' => $config['route'].' postback', 'message' => 'Unauthorized IP: '.$remoteIp.' | Allowed IPs: '.$config['ip'], 'ip' => $remoteIp]);

            return response("Unauthorized IP: {$remoteIp}", 401);
        }

        $jid = $request->input('postback');
        if (! $jid) {
            Error::log(['subject' => $config['route'].' postback', 'message' => 'Missing user ID | IP: '.$remoteIp, 'ip' => $remoteIp]);

            return response('Missing user ID', 400);
        }

        $user = User::where('jid', (int) $jid)->first();
        if (! $user) {
            Error::log(['subject' => $config['route'].' postback', 'message' => 'User not found. User ID: '.$jid.' | IP: '.$remoteIp, 'jid' => (int) $jid, 'ip' => $remoteIp]);

            return response('User not found', 200);
        }

        // Cooldown row locked + re-checked in-transaction: concurrent postbacks
        // serialize and only one grants the reward per timeout window.
        $timeout = $config['timeout'] ?? 12;
        $rewardAmount = $config['reward'] ?? 0;

        try {
            $outcome = DB::transaction(function () use ($user, $rewardAmount, $config, $jid, $remoteIp, $timeout) {
                $now = Carbon::now();

                $voteLog = Vote::where('jid', $jid)->where('site', $config['route'])->lockForUpdate()->first();
                if ($voteLog && $voteLog->expire && $now->lessThan(Carbon::parse($voteLog->expire))) {
                    return 'cooldown:'.$voteLog->expire;
                }

                TbUser::updateSilk($user->jid, $config['type'] ?? 0, $rewardAmount);

                Donate::log([
                    'method' => "Vote [{$config['name']}]",
                    'value' => $rewardAmount,
                    'jid' => $user->jid,
                ]);

                Vote::updateOrCreate(['jid' => $jid, 'site' => $config['route']], ['ip' => $remoteIp, 'expire' => $now->addHours($timeout)]);

                return 'ok';
            });
        } catch (\Throwable $e) {
            Error::log(['subject' => $config['route'].' postback', 'message' => 'Failed to complete vote: '.$e->getMessage().' | User ID: '.$user->jid.' | Site: '.$config['route'].' | Reward: '.$rewardAmount.' | IP: '.$remoteIp, 'jid' => $user->jid, 'ip' => $remoteIp]);

            throw $e;
        }

        if (str_starts_with($outcome, 'cooldown:')) {
            return response('Cooldown active until '.substr($outcome, 9), 200);
        }

        return response('OK', 200);
    }

    public function postbackArenatop100(Request $request)
    {
        $config = config('vote.arenatop100');
        // Only the TrustProxies-resolved IP; CF-Connecting-IP is client-spoofable.
        $remoteIp = $request->ip() ?? '0.0.0.0';

        $allowedIps = array_map('trim', explode(',', $config['ip']));
        if (! in_array($remoteIp, $allowedIps, true)) {
            Error::log(['subject' => $config['route'].' postback', 'message' => 'Unauthorized IP: '.$remoteIp.' | Allowed IPs: '.$config['ip'], 'ip' => $remoteIp]);

            return response("Unauthorized IP: {$remoteIp}", 401);
        }

        $jid = $request->input('userid');
        if (! $jid) {
            Error::log(['subject' => $config['route'].' postback', 'message' => 'Missing user ID | IP: '.$remoteIp, 'ip' => $remoteIp]);

            return response('Missing user ID', 400);
        }

        if ((int) $request->input('voted') !== 1) {
            Error::log(['subject' => $config['route'].' postback', 'message' => 'Vote not successful. User already voted today. User ID: '.$jid.' | IP: '.$remoteIp, 'jid' => (int) $jid, 'ip' => $remoteIp]);

            return response("User $jid voted already today!", 200);
        }

        $user = User::where('jid', (int) $jid)->first();
        if (! $user) {
            Error::log(['subject' => $config['route'].' postback', 'message' => 'User not found. User ID: '.$jid.' | IP: '.$remoteIp, 'jid' => (int) $jid, 'ip' => $remoteIp]);

            return response('User not found', 200);
        }

        // Cooldown row locked + re-checked in-transaction: concurrent postbacks
        // serialize and only one grants the reward per timeout window.
        $timeout = $config['timeout'] ?? 12;
        $rewardAmount = $config['reward'] ?? 0;

        try {
            $outcome = DB::transaction(function () use ($user, $rewardAmount, $config, $jid, $remoteIp, $timeout) {
                $now = Carbon::now();

                $voteLog = Vote::where('jid', $jid)->where('site', $config['route'])->lockForUpdate()->first();
                if ($voteLog && $voteLog->expire && $now->lessThan(Carbon::parse($voteLog->expire))) {
                    return 'cooldown:'.$voteLog->expire;
                }

                TbUser::updateSilk($user->jid, $config['type'] ?? 0, $rewardAmount);

                Donate::log([
                    'method' => "Vote [{$config['name']}]",
                    'value' => $rewardAmount,
                    'jid' => $user->jid,
                ]);

                Vote::updateOrCreate(['jid' => $jid, 'site' => $config['route']], ['ip' => $remoteIp, 'expire' => $now->addHours($timeout)]);

                return 'ok';
            });
        } catch (\Throwable $e) {
            Error::log(['subject' => $config['route'].' postback', 'message' => 'Failed to complete vote: '.$e->getMessage().' | User ID: '.$user->jid.' | Site: '.$config['route'].' | Reward: '.$rewardAmount.' | IP: '.$remoteIp, 'jid' => $user->jid, 'ip' => $remoteIp]);

            throw $e;
        }

        if (str_starts_with($outcome, 'cooldown:')) {
            return response('Cooldown active until '.substr($outcome, 9), 200);
        }

        return response('OK', 200);
    }

    public function postbackSilkroadservers(Request $request)
    {
        $config = config('vote.silkroadservers');
        // Only the TrustProxies-resolved IP; CF-Connecting-IP is client-spoofable.
        $remoteIp = $request->ip() ?? '0.0.0.0';

        $allowedIps = array_map('trim', explode(',', $config['ip']));
        if (! in_array($remoteIp, $allowedIps, true)) {
            Error::log(['subject' => $config['route'].' postback', 'message' => 'Unauthorized IP: '.$remoteIp.' | Allowed IPs: '.$config['ip'], 'ip' => $remoteIp]);

            return response("Unauthorized IP: {$remoteIp}", 401);
        }

        $jid = $request->input('userid');
        if (! $jid) {
            Error::log(['subject' => $config['route'].' postback', 'message' => 'Missing user ID | IP: '.$remoteIp, 'ip' => $remoteIp]);

            return response('Missing user ID', 400);
        }

        $user = User::where('jid', (int) $jid)->first();
        if (! $user) {
            Error::log(['subject' => $config['route'].' postback', 'message' => 'User not found. User ID: '.$jid.' | IP: '.$remoteIp, 'jid' => (int) $jid, 'ip' => $remoteIp]);

            return response('User not found', 200);
        }

        // Cooldown row locked + re-checked in-transaction: concurrent postbacks
        // serialize and only one grants the reward per timeout window.
        $timeout = $config['timeout'] ?? 12;
        $rewardAmount = $config['reward'] ?? 0;

        try {
            $outcome = DB::transaction(function () use ($user, $rewardAmount, $config, $jid, $remoteIp, $timeout) {
                $now = Carbon::now();

                $voteLog = Vote::where('jid', $jid)->where('site', $config['route'])->lockForUpdate()->first();
                if ($voteLog && $voteLog->expire && $now->lessThan(Carbon::parse($voteLog->expire))) {
                    return 'cooldown:'.$voteLog->expire;
                }

                TbUser::updateSilk($user->jid, $config['type'] ?? 0, $rewardAmount);

                Donate::log([
                    'method' => "Vote [{$config['name']}]",
                    'value' => $rewardAmount,
                    'jid' => $user->jid,
                ]);

                Vote::updateOrCreate(['jid' => $jid, 'site' => $config['route']], ['ip' => $remoteIp, 'expire' => $now->addHours($timeout)]);

                return 'ok';
            });
        } catch (\Throwable $e) {
            Error::log(['subject' => $config['route'].' postback', 'message' => 'Failed to complete vote: '.$e->getMessage().' | User ID: '.$user->jid.' | Site: '.$config['route'].' | Reward: '.$rewardAmount.' | IP: '.$remoteIp, 'jid' => $user->jid, 'ip' => $remoteIp]);

            throw $e;
        }

        if (str_starts_with($outcome, 'cooldown:')) {
            return response('Cooldown active until '.substr($outcome, 9), 200);
        }

        return response('OK', 200);
    }

    public function postbackPrivateserver(Request $request)
    {
        $config = config('vote.privateserver');
        // Only the TrustProxies-resolved IP; CF-Connecting-IP is client-spoofable.
        $remoteIp = $request->ip() ?? '0.0.0.0';

        $allowedIps = array_map('trim', explode(',', $config['ip']));
        if (! in_array($remoteIp, $allowedIps, true)) {
            Error::log(['subject' => $config['route'].' postback', 'message' => 'Unauthorized IP: '.$remoteIp.' | Allowed IPs: '.$config['ip'], 'ip' => $remoteIp]);

            return response("Unauthorized IP: {$remoteIp}", 401);
        }

        $jid = $request->input('userid');
        if (! $jid) {
            Error::log(['subject' => $config['route'].' postback', 'message' => 'Missing user ID | IP: '.$remoteIp, 'ip' => $remoteIp]);

            return response('Missing user ID', 400);
        }

        $user = User::where('jid', (int) $jid)->first();
        if (! $user) {
            Error::log(['subject' => $config['route'].' postback', 'message' => 'User not found. User ID: '.$jid.' | IP: '.$remoteIp, 'jid' => (int) $jid, 'ip' => $remoteIp]);

            return response('User not found', 200);
        }

        // Cooldown row locked + re-checked in-transaction: concurrent postbacks
        // serialize and only one grants the reward per timeout window.
        $timeout = $config['timeout'] ?? 12;
        $rewardAmount = $config['reward'] ?? 0;

        try {
            $outcome = DB::transaction(function () use ($user, $rewardAmount, $config, $jid, $remoteIp, $timeout) {
                $now = Carbon::now();

                $voteLog = Vote::where('jid', $jid)->where('site', $config['route'])->lockForUpdate()->first();
                if ($voteLog && $voteLog->expire && $now->lessThan(Carbon::parse($voteLog->expire))) {
                    return 'cooldown:'.$voteLog->expire;
                }

                TbUser::updateSilk($user->jid, $config['type'] ?? 0, $rewardAmount);

                Donate::log([
                    'method' => "Vote [{$config['name']}]",
                    'value' => $rewardAmount,
                    'jid' => $user->jid,
                ]);

                Vote::updateOrCreate(['jid' => $jid, 'site' => $config['route']], ['ip' => $remoteIp, 'expire' => $now->addHours($timeout)]);

                return 'ok';
            });
        } catch (\Throwable $e) {
            Error::log(['subject' => $config['route'].' postback', 'message' => 'Failed to complete vote: '.$e->getMessage().' | User ID: '.$user->jid.' | Site: '.$config['route'].' | Reward: '.$rewardAmount.' | IP: '.$remoteIp, 'jid' => $user->jid, 'ip' => $remoteIp]);

            throw $e;
        }

        if (str_starts_with($outcome, 'cooldown:')) {
            return response('Cooldown active until '.substr($outcome, 9), 200);
        }

        return response('OK', 200);
    }

    public function postbackVote4rewards(Request $request)
    {
        $config = config('vote.vote4rewards');
        // Only the TrustProxies-resolved IP; CF-Connecting-IP is client-spoofable.
        $remoteIp = $request->ip() ?? '0.0.0.0';

        $allowedIps = array_map('trim', explode(',', $config['ip']));
        if (! in_array($remoteIp, $allowedIps, true)) {
            Error::log(['subject' => $config['route'].' postback', 'message' => 'Unauthorized IP: '.$remoteIp.' | Allowed IPs: '.$config['ip'], 'ip' => $remoteIp]);

            return response("Unauthorized IP: {$remoteIp}", 401);
        }

        $serverId = $request->input('server_uuid');
        $webhookSecret = (string) ($config['webhook_secret'] ?? '');
        $signature = $request->header('X-Webhook-Signature');
        if ($webhookSecret === '' || ! is_string($signature) || ! hash_equals($webhookSecret, $signature)) {
            Error::log(['subject' => $config['route'].' postback', 'message' => 'Wrong signature detected | Server ID: '.($serverId ?? 'missing').' | Voter ID: '.($request->input('voter_id') ?? 'missing').' | Signature present: '.(is_string($signature) ? 'yes' : 'no').' | IP: '.$remoteIp, 'ip' => $remoteIp]);

            return response('Wrong Signature', 403);
        }

        $jid = $request->input('voter_id');
        if (! $jid) {
            Error::log(['subject' => $config['route'].' postback', 'message' => 'Missing user ID | IP: '.$remoteIp, 'ip' => $remoteIp]);

            return response('Missing user ID', 400);
        }

        $user = User::where('jid', (int) $jid)->first();
        if (! $user) {
            Error::log(['subject' => $config['route'].' postback', 'message' => 'User not found. User ID: '.$jid.' | IP: '.$remoteIp, 'jid' => (int) $jid, 'ip' => $remoteIp]);

            return response('User not found', 200);
        }

        // Cooldown row locked + re-checked in-transaction (see other handlers).
        $timeout = $config['timeout'] ?? 6;
        $rewardAmount = $config['reward'] ?? 0;

        try {
            $outcome = DB::transaction(function () use ($user, $rewardAmount, $config, $jid, $remoteIp, $timeout) {
                $now = Carbon::now();

                $voteLog = Vote::where('jid', $jid)->where('site', $config['route'])->lockForUpdate()->first();
                if ($voteLog && $voteLog->expire && $now->lessThan(Carbon::parse($voteLog->expire))) {
                    return 'cooldown:'.$voteLog->expire;
                }

                TbUser::updateSilk($user->jid, $config['type'] ?? 0, $rewardAmount);

                Donate::log([
                    'method' => "Vote [{$config['name']}]",
                    'value' => $rewardAmount,
                    'jid' => $user->jid,
                ]);

                Vote::updateOrCreate(['jid' => $jid, 'site' => $config['route']], ['ip' => $remoteIp, 'expire' => $now->addHours($timeout)]);

                return 'ok';
            });
        } catch (\Throwable $e) {
            Error::log(['subject' => $config['route'].' postback', 'message' => 'Failed to complete vote: '.$e->getMessage().' | User ID: '.$user->jid.' | Site: '.$config['route'].' | Reward: '.$rewardAmount.' | IP: '.$remoteIp, 'jid' => $user->jid, 'ip' => $remoteIp]);

            throw $e;
        }

        if (str_starts_with($outcome, 'cooldown:')) {
            return response('Cooldown active until '.substr($outcome, 9), 200);
        }

        return response('OK', 200);
    }
}
