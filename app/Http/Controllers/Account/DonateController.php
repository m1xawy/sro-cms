<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\SRO\Account\SkSilkBuyList;
use App\Models\SRO\Portal\AphChangedSilk;
use App\Services\DonateService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DonateController extends Controller
{
    public function index(): View
    {
        return view('account.donate.index', ['data' => config('donate')]);
    }

    public function show(string $method): mixed
    {
        $config = config("donate.{$method}");
        if (!$config || !$config['enabled']) {
            return back()->withErrors(['error' => 'Payment method not found or disabled.']);
        }

        if (!view()->exists("account.donate.{$method}")) {
            return back()->withErrors(['error' => 'View file for the payment method is missing.']);
        }

        return view("account.donate.{$method}", ['data' => $config]);
    }

    public function process(string $method, Request $request, DonateService $donateService): mixed
    {
        $config = config("donate.{$method}");
        if (!$config || !$config['enabled']) {
            return back()->withErrors(['error' => 'Payment method not found or disabled.']);
        }

        $methodName = 'process' . ucfirst($method);
        if (!method_exists($donateService, $methodName)) {
            return back()->withErrors(['error' => 'Invalid payment method.']);
        }

        return $donateService->$methodName($request);
    }

    public function callback(string $method, Request $request, DonateService $donateService): mixed
    {
        $config = config("donate.{$method}");
        if (!$config || !$config['enabled']) {
            return back()->withErrors(['error' => 'Payment method not found or disabled.']);
        }

        $methodName = 'callback' . ucfirst($method);
        if (!method_exists($donateService, $methodName)) {
            return back()->withErrors(['error' => 'Invalid payment method.']);
        }

        return $donateService->$methodName($request);
    }

    public function webhook(string $method, Request $request, DonateService $donateService): mixed
    {
        $config = config("donate.{$method}");
        if (!$config || !$config['enabled']) {
            return response('Payment method not found or disabled.', 403);
        }

        $methodName = 'webhook' . ucfirst($method);
        if (!method_exists($donateService, $methodName)) {
            return response('Invalid payment method.', 403);
        }

        return $donateService->$methodName($request);
    }

    public function history(Request $request): View
    {
        if (config('global.server.version') === 'vSRO') {
            $data = SkSilkBuyList::getSilkBuyList($request->user()->jid, 25, $request->get('page', 1));
        }else {
            $data = AphChangedSilk::getSilkHistory($request->user()->jid, 25, $request->get('page', 1));
        }

        return view('account.donate.history', [
            'user' => $request->user(),
            'data' => $data,
        ]);
    }
}
