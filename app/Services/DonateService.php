<?php

namespace App\Services;

use App\Models\Donate;
use App\Models\SRO\Account\TbUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DonateService
{
    public function processPaypal(Request $request)
    {
        $config = config('donate.paypal');

        $request->validate([
            'price' => 'required|numeric|min:0.01',
        ]);

        $package = collect($config['package'])->firstWhere('price', $request->input('price'));
        if (! $package) {
            return back()->withErrors(['paypal' => 'Invalid package selected.'])->withInput();
        }

        $tokenResponse = Http::withBasicAuth($config['client_id'], $config['client_secret'])
            ->asForm()
            ->post($config['endpoint'].'/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        if (! $tokenResponse->successful()) {
            return back()->withErrors(['paypal' => 'Unable to get PayPal access token.'])->withInput();
        }

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$tokenResponse->json('access_token'),
            'PayPal-Request-Id' => (string) Str::uuid(),
        ])->post($config['endpoint'].'/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'payment_source' => [
                'paypal' => [
                    'experience_context' => [
                        'return_url' => route('callback', ['method' => 'paypal']),
                        'cancel_url' => route('account.donate'),
                    ],
                ],
            ],
            'purchase_units' => [
                [
                    'invoice_id' => (string) Str::uuid(),
                    'custom_id' => (string) auth()->id(),
                    'amount' => [
                        'currency_code' => strtoupper($config['currency']),
                        'value' => number_format($package['price'], 2, '.', ''),
                    ],
                ],
            ],
        ]);

        if (! $response->successful()) {
            return back()->withErrors(['paypal' => 'Payment Failed: '.($response->json('error_description') ?? 'An error occurred')])->withInput();
        }

        $approvalLink = collect($response->json('links'))->firstWhere('rel', 'payer-action') ?? collect($response->json('links'))->firstWhere('rel', 'approve');
        if (! $approvalLink) {
            return back()->withErrors(['paypal' => 'Payment Failed: Approval link not found.'])->withInput();
        }

        $separator = str_contains($approvalLink['href'], '?') ? '&' : '?';

        return redirect()->away($approvalLink['href'].$separator.'useraction=commit');
    }

    public function callbackPaypal(Request $request)
    {
        $config = config('donate.paypal');

        $request->validate(['token' => 'required|string']);

        $tokenResponse = Http::withBasicAuth($config['client_id'], $config['client_secret'])
            ->asForm()
            ->post($config['endpoint'].'/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        if (! $tokenResponse->successful()) {
            return back()->withErrors(['paypal' => 'Unable to get PayPal access token.'])->withInput();
        }

        $orderResponse = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$tokenResponse->json('access_token'),
        ])->get($config['endpoint'].'/v2/checkout/orders/'.$request->get('token'));

        if (! $orderResponse->successful()) {
            return back()->withErrors(['paypal' => 'Payment Failed: '.($orderResponse->json('error_description') ?? 'An error occurred')])->withInput();
        }

        if ($orderResponse->json('status') === 'APPROVED') {
            $captureResponse = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.$tokenResponse->json('access_token'),
                'PayPal-Request-Id' => (string) Str::uuid(),
            ])->post($config['endpoint'].'/v2/checkout/orders/'.$request->get('token').'/capture', new \stdClass);

            if (! $captureResponse->successful()) {
                return back()->withErrors(['paypal' => 'Payment Failed: '.($captureResponse->json('message') ?? 'An error occurred')])->withInput();
            }

            if ($captureResponse->json('status') !== 'COMPLETED') {
                return back()->withErrors(['paypal' => 'Payment not completed. Status: '.$captureResponse->json('status')])->withInput();
            }

            $order = $captureResponse->json();
        } else {
            if ($orderResponse->json('status') !== 'COMPLETED') {
                return back()->withErrors(['paypal' => 'Payment not completed. Status: '.$orderResponse->json('status')])->withInput();
            }

            $order = $orderResponse->json();
        }

        if (Donate::where('transaction_id', $order['id'])->where('status', 'success')->exists()) {
            return redirect()->route('account.donate')->with('success', __('Payment already processed.'));
        }

        $paidAmount = $order['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? null;
        if (! $paidAmount) {
            return back()->withErrors(['paypal' => 'Unable to determine paid amount.'])->withInput();
        }

        $package = collect($config['package'])->firstWhere('price', $paidAmount);
        if (! $package) {
            return back()->withErrors(['paypal' => 'Invalid package amount: $'.$paidAmount])->withInput();
        }

        $customId = $orderResponse->json('purchase_units.0.custom_id')
            ?? $order['purchase_units'][0]['custom_id']
            ?? $order['purchase_units'][0]['payments']['captures'][0]['custom_id']
            ?? null;
        if (! $customId) {
            return back()->withErrors(['paypal' => 'Unable to identify user.'])->withInput();
        }

        $user = User::find($customId);
        if (! $user) {
            return back()->withErrors(['paypal' => 'User not found.'])->withInput();
        }

        // Idempotent claim on UNIQUE(transaction_id): the row is written
        // BEFORE Silk is credited, so concurrent callbacks serialize here and
        // only one proceeds. Fail-closed: a crash after insert needs manual
        // reconcile instead of risking double Silk on retry.
        try {
            Donate::log([
                'method' => 'PayPal',
                'transaction_id' => $order['id'],
                'status' => 'success',
                'amount' => $package['price'],
                'type' => $package['type'],
                'value' => $package['value'],
                'jid' => $user->jid,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if ((string) $e->getCode() === '23000') {
                return redirect()->route('account.donate')->with('success', __('Payment already processed.'));
            }

            throw $e;
        }

        TbUser::updateSilk($user->jid, $package['type'], $package['value']);

        return redirect()->route('account.donate')->with('success', number_format($package['value']).' '.__('Silk has been added to your account.'));
    }

    public function processStripe(Request $request)
    {
        $config = config('donate.stripe');

        $request->validate([
            'price' => 'required|numeric|min:0.01',
        ]);

        $package = collect($config['package'])->firstWhere('price', $request->input('price'));
        if (! $package) {
            return back()->withErrors(['stripe' => 'Invalid package selected.'])->withInput();
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$config['secret_key'],
        ])->asForm()->post($config['endpoint'].'/v1/checkout/sessions', [
            'payment_method_types[]' => 'card',
            'line_items[0][price_data][currency]' => strtolower($config['currency']),
            'line_items[0][price_data][product_data][name]' => $package['name'],
            'line_items[0][price_data][unit_amount]' => $package['price'] * 100,
            'line_items[0][quantity]' => 1,
            'mode' => 'payment',
            'success_url' => route('callback', ['method' => 'stripe', 'status' => 'success']),
            'cancel_url' => route('callback', ['method' => 'stripe', 'status' => 'fail']),
            'metadata[jid]' => Auth::user()->jid,
            'metadata[package_price]' => $package['price'],
        ]);

        if (! $response->successful()) {
            return back()->withErrors(['stripe' => 'Payment Failed: '.($response->json('error.message') ?? 'An error occurred')])->withInput();
        }

        if (! $response->json('url') || ! $response->json('id')) {
            return back()->withErrors(['stripe' => 'Payment Failed: An error occurred'])->withInput();
        }

        Donate::log([
            'method' => 'Stripe',
            'transaction_id' => $response->json('id'),
            'status' => 'pending',
            'amount' => $package['price'],
            'type' => $package['type'],
            'value' => $package['value'],
            'jid' => Auth::user()->jid,
        ]);

        return redirect()->away($response->json('url'));
    }

    public function callbackStripe(Request $request)
    {
        $status = $request->query('status');

        if ($status === 'success') {
            return redirect()->route('account.donate')->with('success', __('Payment successful! Your silk will be added shortly.'));
        }

        return redirect()->route('account.donate')->withErrors(['stripe' => 'Payment was cancelled or failed. Please try again.']);
    }

    public function webhookStripe(Request $request)
    {
        $config = config('donate.stripe');
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        $hash = null;
        foreach (explode(',', $sigHeader) as $part) {
            if (str_starts_with(trim($part), 'v1=')) {
                $hash = substr(trim($part), 3);
                break;
            }
        }

        $timestamp = null;
        foreach (explode(',', $sigHeader) as $part) {
            if (str_starts_with(trim($part), 't=')) {
                $timestamp = substr(trim($part), 2);
                break;
            }
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $config['webhook_secret']);
        if (! hash_equals($expected, $hash ?? '')) {
            return response('Invalid signature', 400);
        }

        $event = $request->json()->all();
        if (($event['type'] ?? '') !== 'checkout.session.completed') {
            return response('OK', 200);
        }

        $session = $event['data']['object'];
        if (($session['payment_status'] ?? '') !== 'paid') {
            return response('OK', 200);
        }

        $donate = Donate::where('transaction_id', (string) $session['id'])->where('status', 'pending')->first();
        if (! $donate) {
            return response('Transaction not found or already processed.', 409);
        }

        $package = collect($config['package'])->firstWhere('price', $donate->amount);
        if (! $package) {
            return response('Invalid package price', 422);
        }

        $user = User::where('jid', $donate->jid)->first();
        if (! $user) {
            return response('User not found', 404);
        }

        try {
            DB::transaction(function () use ($user, $package, $donate) {
                $row = Donate::whereKey($donate->id)->lockForUpdate()->first();
                if (! $row || $row->status !== 'pending') {
                    throw new \RuntimeException('Transaction not found or already processed.');
                }

                TbUser::updateSilk($user->jid, $package['type'], $package['value']);
                $row->update(['status' => 'success']);
            });
        } catch (\RuntimeException $e) {
            return response('Transaction not found or already processed.', 409);
        }

        return response('OK', 200);
    }

    public function processNowpayments(Request $request)
    {
        $config = config('donate.nowpayments');
        $user = Auth::user();

        $request->validate([
            'price' => 'required|numeric|min:1',
        ]);

        $package = collect($config['package'])->firstWhere('price', $request->input('price'));
        if (! $package) {
            return back()->withErrors(['nowpayments' => 'Invalid package selected.'])->withInput();
        }

        $response = Http::withHeaders([
            'x-api-key' => $config['api_key'],
            'Content-Type' => 'application/json',
        ])->post($config['endpoint'].'/invoice', [
            'price_amount' => $package['price'],
            'price_currency' => strtolower($config['currency']),
            'order_id' => uniqid().rand(100, 999),
            'order_description' => $package['name'],
            'ipn_callback_url' => route('webhook', ['method' => 'nowpayments']),
            'success_url' => route('callback', ['method' => 'nowpayments', 'status' => 'success']),
            'cancel_url' => route('callback', ['method' => 'nowpayments', 'status' => 'fail']),
        ]);

        if (! $response->successful()) {
            return back()->withErrors(['nowpayments' => 'Payment Failed: '.($response->json('message') ?? 'An error occurred')])->withInput();
        }

        if (! $response->json('invoice_url') || ! $response->json('id')) {
            return back()->withErrors(['nowpayments' => 'Payment Failed: An error occurred'])->withInput();
        }

        Donate::log([
            'method' => 'NOWPayments',
            'transaction_id' => $response->json('id'),
            'status' => 'pending',
            'amount' => $package['price'],
            'type' => $package['type'],
            'value' => $package['value'],
            'jid' => $user->jid,
        ]);

        return redirect()->away($response->json('invoice_url'));
    }

    public function callbackNowpayments(Request $request)
    {
        $status = $request->query('status');

        if ($status === 'success') {
            return redirect()->route('account.donate')->with('success', __('Payment successful! Your silk will be added shortly.'));
        }

        return redirect()->route('account.donate')->withErrors(['nowpayments' => 'Payment failed or was cancelled. Please try again.']);
    }

    public function webhookNowpayments(Request $request)
    {
        $config = config('donate.nowpayments');
        $data = $request->json()->all();

        if (! $data) {
            return response('Invalid payload', 400);
        }

        $received = $request->header('x-nowpayments-sig');
        $sorted = $data;
        ksort($sorted);
        $hash = hash_hmac('sha512', json_encode($sorted, JSON_UNESCAPED_UNICODE), $config['ipn_secret']);
        if (! hash_equals($hash, $received)) {
            return response('Invalid signature', 400);
        }

        if (($data['payment_status'] ?? '') !== 'finished') {
            return response('OK', 200);
        }

        $donate = Donate::where('transaction_id', (string) $data['invoice_id'])->where('status', 'pending')->first();
        if (! $donate) {
            return response('Transaction not found or already processed.', 409);
        }

        $package = collect($config['package'])->firstWhere('price', $donate->amount);
        if (! $package) {
            return response('Invalid package price', 422);
        }

        $user = User::where('jid', $donate->jid)->first();
        if (! $user) {
            return response('User not found', 404);
        }

        try {
            DB::transaction(function () use ($user, $package, $donate) {
                $row = Donate::whereKey($donate->id)->lockForUpdate()->first();
                if (! $row || $row->status !== 'pending') {
                    throw new \RuntimeException('Transaction not found or already processed.');
                }

                TbUser::updateSilk($user->jid, $package['type'], $package['value']);
                $row->update(['status' => 'success']);
            });
        } catch (\RuntimeException $e) {
            return response('Transaction not found or already processed.', 409);
        }

        return response('OK', 200);
    }

    public function processFawaterk(Request $request)
    {
        $config = config('donate.fawaterk');
        $user = Auth::user();

        $request->validate([
            'price' => 'required|numeric|min:5',
        ]);

        $package = collect($config['package'])->firstWhere('price', $request->input('price'));
        if (! $package) {
            return back()->withErrors(['fawaterk' => 'Invalid package selected.'])->withInput();
        }

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$config['api_key'],
        ])->post($config['endpoint'].'/api/v2/createInvoiceLink', [
            'cartTotal' => $package['price'],
            'currency' => $config['currency'],
            'customer' => [
                'first_name' => $user->username,
                'email' => $user->email,
            ],
            'redirectionUrls' => [
                'successUrl' => route('callback', ['method' => 'fawaterk', 'status' => 'success']),
                'failUrl' => route('callback', ['method' => 'fawaterk', 'status' => 'fail']),
                'pendingUrl' => route('callback', ['method' => 'fawaterk', 'status' => 'pending']),
            ],
            'cartItems' => [
                [
                    'name' => $package['name'],
                    'price' => $package['price'],
                    'quantity' => 1,
                ],
            ],
        ]);

        if (! $response->successful() || $response->json('status') !== 'success') {
            return back()->withErrors(['fawaterk' => 'Payment Failed: '.(is_array($response->json('message')) ? collect($response->json('message'))->flatten()->first() : $response->json('message')) ?? 'An error occurred'])->withInput();
        }

        if (! $response->json('data.url') || ! $response->json('data.invoiceId')) {
            return back()->withErrors(['fawaterk' => 'Payment Failed: An error occurred'])->withInput();
        }

        $existing = Donate::where('transaction_id', (string) $response->json('data.invoiceId'))->first();
        if ($existing && $existing->status === 'success') {
            return back()->withErrors(['fawaterk' => 'This invoice was already paid.'])->withInput();
        }

        Donate::updateOrCreate(['transaction_id' => (string) $response->json('data.invoiceId')], [
            'method' => 'Fawaterk',
            'status' => 'pending',
            'amount' => $package['price'],
            'type' => $package['type'],
            'value' => $package['value'],
            'desc' => '',
            'jid' => $user->jid,
            'ip' => request()->ip() ?? null,
        ]);

        return redirect()->away($response->json('data.url'));
    }

    public function callbackFawaterk(Request $request)
    {
        $status = $request->query('status');

        if ($status === 'success') {
            return redirect()->route('account.donate')->with('success', __('Payment successful! Your silk will be added shortly.'));
        }

        if ($status === 'fail') {
            return redirect()->route('account.donate')->withErrors(['fawaterk' => 'Payment failed. Please try again.']);
        }

        if ($status === 'pending') {
            return redirect()->route('account.donate')->with('info', __('Payment is pending. Your silk will be added once confirmed.'));
        }

        return redirect()->route('account.donate')->withErrors(['fawaterk' => 'Unknown payment status.']);
    }

    public function webhookFawaterk(Request $request)
    {
        $config = config('donate.fawaterk');
        $data = $request->all();

        if (! $data) {
            return response('Invalid payload', 400);
        }

        $hash = hash_hmac('sha256', 'InvoiceId='.$data['invoice_id'].'&InvoiceKey='.$data['invoice_key'].'&PaymentMethod='.$data['payment_method'], $config['provider_key']);
        if (! hash_equals($hash, $data['hashKey'])) {
            return response('Invalid Hash', 400);
        }

        if (($data['invoice_status'] ?? '') !== 'paid') {
            return response('Not paid', 200);
        }

        $donate = Donate::where('transaction_id', (string) $data['invoice_id'])->where('status', 'pending')->first();
        if (! $donate) {
            return response('Transaction not found or already processed.', 409);
        }

        $package = collect($config['package'])->firstWhere('price', $donate->amount);
        if (! $package) {
            return response('Invalid package price', 422);
        }

        $user = User::where('jid', $donate->jid)->first();
        if (! $user) {
            return response('User not found', 404);
        }

        try {
            DB::transaction(function () use ($user, $package, $donate) {
                $row = Donate::whereKey($donate->id)->lockForUpdate()->first();
                if (! $row || $row->status !== 'pending') {
                    throw new \RuntimeException('Transaction not found or already processed.');
                }

                TbUser::updateSilk($user->jid, $package['type'], $package['value']);
                $row->update(['status' => 'success']);
            });
        } catch (\RuntimeException $e) {
            return response('Transaction not found or already processed.', 409);
        }

        return response('OK', 200);
    }

    public function processMaxicard(Request $request)
    {
        $config = config('donate.maxicard');

        $request->validate([
            'code' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = Auth::user();
        $escape = fn ($value) => htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $xml = trim('<?xml version="1.0" encoding="utf-8"?>
        <APIRequest>
            <params>
                <username>'.$escape($config['api_key']).'</username>
                <password>'.$escape($config['api_password']).'</password>
                <cmd>epinadd</cmd>
                <epinusername>'.$escape($user->username).'</epinusername>
                <epincode>'.$escape($request->code).'</epincode>
                <epinpass>'.$escape($request->password).'</epinpass>
            </params>
        </APIRequest>');

        $response = Http::asForm()->timeout(20)->post($config['endpoint'], [
            'data' => urlencode($xml),
        ]);

        if (! $response->successful()) {
            return back()->withErrors(['maxicard' => 'Payment Failed: An error occurred'])->withInput();
        }

        $xml = simplexml_load_string($response->body());
        if ($xml === false) {
            return back()->withErrors(['maxicard' => 'Invalid response from payment provider.'])->withInput();
        }

        if (trim($xml->params->durum) !== 'ok' || intval(trim($xml->params->siparis_no)) <= 0) {
            return back()->withErrors(['maxicard' => 'Payment Failed: '.trim($xml->params->aciklama)])->withInput();
        }

        $package = collect($config['package'])->firstWhere('price', intval(preg_replace('/[^0-9]/', '', $xml->params->tutar)));
        if (! $package) {
            return back()->withErrors(['maxicard' => 'Invalid package price.'])->withInput();
        }

        // Same e-PIN must never credit twice: claim the provider order id
        // BEFORE crediting (see PayPal). Failed provider responses create no
        // row, so legitimate retries still proceed.
        try {
            Donate::log([
                'method' => 'MaxiCard',
                'transaction_id' => intval(trim($xml->params->siparis_no)),
                'status' => 'success',
                'amount' => $package['price'],
                'type' => $package['type'],
                'value' => $package['value'],
                'jid' => $user->jid,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if ((string) $e->getCode() === '23000') {
                return redirect()->route('account.donate')->with('success', __('Payment already processed.'));
            }

            throw $e;
        }

        TbUser::updateSilk($user->jid, $package['type'], $package['value']);

        return redirect()->route('account.donate')->with('success', number_format($package['value']).' '.__('Silk has been added to your account.'));
    }

    public function processHipocard(Request $request)
    {
        $config = config('donate.hipocard');

        $request->validate([
            'code' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = Auth::user();
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'api-key' => $config['api_key'],
            'api-secret' => $config['api_password'],
        ])->post($config['endpoint'], [
            'epin_code' => $request->code,
            'epin_secret' => $request->password,
            'player_name' => $user->username,
            'used_ip' => $request->ip(),
        ]);

        if (! $response->successful() || $response->json('success') !== true) {
            return back()->withErrors(['hipocard' => 'Payment Failed: '.($response->json('message') ?? 'An error occurred')])->withInput();
        }

        $package = collect($config['package'])->firstWhere('price', intval($response->json('data.total_sales')));
        if (! $package) {
            return back()->withErrors(['hipocard' => 'Invalid package price.'])->withInput();
        }

        // Deterministic idempotency key from the e-PIN (a fresh random id per
        // request would defeat UNIQUE(transaction_id) and let one e-PIN pay
        // twice). Insert BEFORE crediting (see PayPal).
        $transactionId = 'hipocard-'.substr(hash('sha256', 'hipocard|'.trim($request->code)), 0, 32);

        try {
            Donate::log([
                'method' => 'HipoCard',
                'transaction_id' => $transactionId,
                'status' => 'success',
                'amount' => $package['price'],
                'type' => $package['type'],
                'value' => $package['value'],
                'jid' => $user->jid,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if ((string) $e->getCode() === '23000') {
                return redirect()->route('account.donate')->with('success', __('Payment already processed.'));
            }

            throw $e;
        }

        TbUser::updateSilk($user->jid, $package['type'], $package['value']);

        return redirect()->route('account.donate')->with('success', number_format($package['value']).' '.__('Silk has been added to your account.'));
    }

    public function processHipopay(Request $request)
    {
        $config = config('donate.hipopay');

        $request->validate([
            'price' => 'required|numeric|min:0.01',
        ]);

        $package = collect($config['package'])->firstWhere('price', $request->input('price'));
        if (! $package) {
            return back()->withErrors(['hipopay' => 'Invalid package selected.'])->withInput();
        }

        $user = Auth::user();
        $hash = base64_encode(hash_hmac('sha256', $user->jid.trim($user->email).$user->username.$config['api_key'], $config['api_password'], true));

        $response = Http::asForm()->post($config['endpoint'], [
            'api_key' => $config['api_key'],
            'api_secret' => $config['api_password'],
            'user_id' => $user->jid,
            'username' => $user->username,
            'email' => $user->email,
            'ip_address' => $request->ip(),
            'hash' => $hash,
            'pro' => true,
            'product' => [
                'name' => $package['name'],
                'price' => $package['price'] * 100,
                'reference_id' => uniqid().rand(100, 999),
                'commission_type' => $config['commission_type'],
            ],
        ]);

        if ($response->successful() && $response->json('success') === true) {
            $paymentUrl = $response->json('data.payment_url');
            if ($paymentUrl) {
                Donate::log([
                    'method' => 'HipoPay',
                    'status' => 'pending',
                    'amount' => $package['price'],
                    'type' => $package['type'],
                    'value' => $package['value'],
                    'jid' => $user->jid,
                ]);

                return redirect()->away($paymentUrl);
            }
        }

        return back()->withErrors(['hipopay' => 'Payment Failed: '.($response->json('message') ?? 'An error occurred')])->withInput();
    }

    public function webhookHipopay(Request $request)
    {
        $config = config('donate.hipopay');

        $data = $request->json()->all();
        if (! $data) {
            return response('Invalid payload', 400);
        }

        $user = User::where('jid', $data['user_id'])->first();
        if (! $user) {
            return response('User not found', 404);
        }

        $hash = base64_encode(hash_hmac('sha256', $data['transaction_id'].$data['user_id'].$data['email'].$data['name'].$data['status'].$config['api_key'], $config['api_password'], true));
        if (! hash_equals($hash, $data['hash'])) {
            return response('Invalid Hash', 400);
        }

        if (Donate::where('transaction_id', (string) $data['transaction_id'])->where('status', 'success')->exists()) {
            return response('Transaction already processed.', 409);
        }

        if ($data['status'] !== 'success') {
            return response('Payment not successful', 422);
        }

        $donate = Donate::where('method', 'HipoPay')->where('jid', $user->jid)->where('status', 'pending')->where('amount', intval($data['payment_total'] / 100))->latest('id')->first();
        if (! $donate) {
            return response('No matching pending transaction.', 422);
        }

        $package = collect($config['package'])->firstWhere('price', (int) $donate->amount);
        if (! $package) {
            return response('Invalid package price', 422);
        }

        try {
            DB::transaction(function () use ($user, $package, $data, $donate) {
                $row = Donate::whereKey($donate->id)->lockForUpdate()->first();
                if (! $row || $row->status !== 'pending') {
                    throw new \RuntimeException('Transaction already processed.');
                }

                TbUser::updateSilk($user->jid, $package['type'], $package['value']);
                $row->update([
                    'status' => 'success',
                    'transaction_id' => (string) $data['transaction_id'],
                ]);
            });
        } catch (\RuntimeException $e) {
            return response('Transaction already processed.', 409);
        }

        return response('OK', 200);
    }
}
