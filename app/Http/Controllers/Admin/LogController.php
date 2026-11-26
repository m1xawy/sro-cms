<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Error;
use App\Models\Donate;
use App\Models\Referral;
use App\Models\SRO\Account\SmcLog;
use App\Models\Vote;
use Illuminate\Http\Request;

class LogController extends Controller
{
    public function index(Request $request)
    {
        $query = Error::query();

        $search = $request->filled('search') ? $request->search : null;
        $query->when($search, fn($q) => $q->where(function ($q2) use ($search) {
            $q2->where('message', 'like', "%{$search}%")
                ->orWhere('subject', 'like', "%{$search}%")
                ->orWhere('jid', 'like', "%{$search}%");
        })
        );

        $data = $query->latest('created_at')->paginate(20);

        return view('admin.logs.error', compact('data'));
    }

    public function donate(Request $request)
    {
        $query = Donate::query();

        $query->when($request->filled('transaction_id'), fn($q) =>
            $q->where('transaction_id', 'like', "%{$request->transaction_id}%")
        );
        $query->when($request->filled('method_type'), fn($q) =>
            $q->where('method', $request->method_type)
        );
        $query->when($request->filled('status'), fn($q) =>
            $q->where('status', $request->status)
        );
        $query->when($request->filled('jid'), fn($q) =>
            $q->where('jid', $request->jid)
        );
        $query->when($request->filled('ip'), fn($q) =>
            $q->where('ip', 'like', "%{$request->ip}%")
        );

        $data = $query->latest()->paginate(20);

        return view('admin.logs.donate', compact('data'));
    }

    public function referral(Request $request)
    {
        $data = Referral::getReferralLogs(20);

        return view('admin.logs.referral', compact('data'));
    }

    public function vote(Request $request)
    {
        $data = Vote::whereNotNull('expire')->latest()->paginate(20);

        return view('admin.logs.vote', compact('data'));
    }

    public function smc(Request $request)
    {
        $query = SmcLog::query();

        $query->when($request->filled('search'), fn($q) => $q->where(function ($q2) use ($request) {
            $search = $request->search;
            $q2->where('szUserID', 'like', "%{$search}%")
                ->orWhere('szLog', 'like', "%{$search}%");
        }));

        $data = $query->latest('dLogDate')->paginate(20);

        return view('admin.logs.smc', compact('data'));
    }
}
