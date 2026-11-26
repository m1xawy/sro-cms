<?php

namespace App\Services;

use App\Models\Donate;
use App\Models\Error;
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

        $request->validate(['price' => 'required|numeric|min:0.01']);

        $user = Auth::user();

        $package = collect($config['package'])->firstWhere('price', $request->input('price'));
        if (! $package) {
            Error::log(['subject' => 'paypal process', 'message' => 'Invalid package selected. Requested price: '.$request->input('price'), 'jid' => $user?->jid, 'ip' => request()->ip()]);

            return back()->withErrors(['paypal' => 'Invalid package selected.'])->withInput();
        }

        $tokenResponse = Http::withBasicAuth($config['client_id'], $config['client_secret'])->asForm()->post($config['endpoint'].'/v1/oauth2/token', ['grant_type' => 'client_credentials']);
        if (! $tokenResponse->successful()) {
            Error::log(['subject' => 'paypal process', 'message' => 'Unable to get PayPal access token: '.$this->apiError($tokenResponse), 'jid' => $user?->jid, 'ip' => request()->ip()]);

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
                    'custom_id' => (string) $user?->id,
                    'amount' => [
                        'currency_code' => strtoupper($config['currency']),
                        'value' => number_format($package['price'], 2, '.', ''),
                    ],
                ],
            ],
        ]);

        if (! $response->successful()) {
            Error::log(['subject' => 'paypal process', 'message' => 'Payment Failed: '.$this->apiError($response).' | Package: '.$package['name'].' ('.$package['price'].')', 'jid' => $user?->jid, 'ip' => request()->ip()]);

            return back()->withErrors(['paypal' => 'Payment Failed: '.($response->json('error_description') ?? 'An error occurred')])->withInput();
        }

        $approvalLink = collect($response->json('links'))->firstWhere('rel', 'payer-action') ?? collect($response->json('links'))->firstWhere('rel', 'approve');
        if (! $approvalLink) {
            Error::log(['subject' => 'paypal process', 'message' => 'Payment Failed: Approval link not found. Order ID: '.($response->json('id') ?? 'unknown').' | '.Str::limit($response->body(), 500), 'jid' => $user?->jid, 'ip' => request()->ip()]);

            return back()->withErrors(['paypal' => 'Payment Failed: Approval link not found.'])->withInput();
        }

        $separator = str_contains($approvalLink['href'], '?') ? '&' : '?';

        return redirect()->away($approvalLink['href'].$separator.'useraction=commit');
    }

    public function callbackPaypal(Request $request)
    {
        $config = config('donate.paypal');

        $request->validate(['token' => 'required|string']);

        $tokenResponse = Http::withBasicAuth($config['client_id'], $config['client_secret'])->asForm()->post($config['endpoint'].'/v1/oauth2/token', ['grant_type' => 'client_credentials']);
        if (! $tokenResponse->successful()) {
            Error::log(['subject' => 'paypal callback', 'message' => 'Unable to get PayPal access token: '.$this->apiError($tokenResponse), 'jid' => Auth::user()?->jid, 'ip' => request()->ip()]);

            return back()->withErrors(['paypal' => 'Unable to get PayPal access token.'])->withInput();
        }

        $orderResponse = Http::withHeaders(['Content-Type' => 'application/json', 'Authorization' => 'Bearer '.$tokenResponse->json('access_token')])->get($config['endpoint'].'/v2/checkout/orders/'.$request->get('token'));
        if (! $orderResponse->successful()) {
            Error::log(['subject' => 'paypal callback', 'message' => 'Payment Failed: '.$this->apiError($orderResponse).' | PayPal order token: '.$request->get('token'), 'jid' => Auth::user()?->jid, 'ip' => request()->ip()]);

            return back()->withErrors(['paypal' => 'Payment Failed: '.($orderResponse->json('error_description') ?? 'An error occurred')])->withInput();
        }

        if ($orderResponse->json('status') === 'APPROVED') {
            $captureResponse = Http::withHeaders(['Content-Type' => 'application/json', 'Authorization' => 'Bearer '.$tokenResponse->json('access_token'), 'PayPal-Request-Id' => (string) Str::uuid()])->post($config['endpoint'].'/v2/checkout/orders/'.$request->get('token').'/capture', new \stdClass);
            if (! $captureResponse->successful()) {
                Error::log(['subject' => 'paypal callback', 'message' => 'Payment Failed: '.$this->apiError($captureResponse).' | Order ID: '.$request->get('token'), 'jid' => Auth::user()?->jid, 'ip' => request()->ip()]);

                return back()->withErrors(['paypal' => 'Payment Failed: '.($captureResponse->json('message') ?? 'An error occurred')])->withInput();
            }

            if ($captureResponse->json('status') !== 'COMPLETED') {
                Error::log(['subject' => 'paypal callback', 'message' => 'Payment not completed. Status: '.$captureResponse->json('status').' | Order ID: '.($captureResponse->json('id') ?? $request->get('token')), 'jid' => Auth::user()?->jid, 'ip' => request()->ip()]);

                return back()->withErrors(['paypal' => 'Payment not completed. Status: '.$captureResponse->json('status')])->withInput();
            }

            $order = $captureResponse->json();
        } else {
            if ($orderResponse->json('status') !== 'COMPLETED') {
                Error::log(['subject' => 'paypal callback', 'message' => 'Payment not completed. Status: '.$orderResponse->json('status').' | Order ID: '.($orderResponse->json('id') ?? $request->get('token')), 'jid' => Auth::user()?->jid, 'ip' => request()->ip()]);

                return back()->withErrors(['paypal' => 'Payment not completed. Status: '.$orderResponse->json('status')])->withInput();
            }

            $order = $orderResponse->json();
        }

        $customId = $orderResponse->json('purchase_units.0.custom_id') ?? $order['purchase_units'][0]['custom_id'] ?? $order['purchase_units'][0]['payments']['captures'][0]['custom_id'] ?? null;
        if (! $customId) {
            Error::log(['subject' => 'paypal callback', 'message' => 'Unable to identify user. Order ID: '.$order['id'].' | Amount: $'.($order['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? 'unknown'), 'jid' => null, 'ip' => request()->ip()]);

            return back()->withErrors(['paypal' => 'Unable to identify user.'])->withInput();
        }

        $alreadyProcessed = Donate::where('transaction_id', $order['id'])->where('status', 'success')->exists();
        if ($alreadyProcessed) {
            Error::log(['subject' => 'paypal callback', 'message' => 'Payment already processed. Order ID: '.$order['id'].' | User ID: '.($customId ?? 'unknown'), 'jid' => is_numeric($customId) ? (int) $customId : null, 'ip' => request()->ip()]);

            return redirect()->route('account.donate')->with('success', __('Payment already processed.'));
        }

        $paidAmount = $order['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? null;
        if (! $paidAmount) {
            Error::log(['subject' => 'paypal callback', 'message' => 'Unable to determine paid amount. Order ID: '.$order['id'].' | User ID: '.($customId ?? 'unknown'), 'jid' => is_numeric($customId) ? (int) $customId : null, 'ip' => request()->ip()]);

            return back()->withErrors(['paypal' => 'Unable to determine paid amount.'])->withInput();
        }

        $package = collect($config['package'])->firstWhere('price', $paidAmount);
        if (! $package) {
            Error::log(['subject' => 'paypal callback', 'message' => 'Invalid package amount: $'.$paidAmount.' | Order ID: '.$order['id'].' | User ID: '.($customId ?? 'unknown'), 'jid' => is_numeric($customId) ? (int) $customId : null, 'ip' => request()->ip()]);

            return back()->withErrors(['paypal' => 'Invalid package amount: $'.$paidAmount])->withInput();
        }

        $user = User::find($customId);
        if (! $user) {
            Error::log(['subject' => 'paypal callback', 'message' => 'User not found. User ID: '.$customId.' | Order ID: '.$order['id'], 'jid' => is_numeric($customId) ? (int) $customId : null, 'ip' => request()->ip()]);

            return back()->withErrors(['paypal' => 'User not found.'])->withInput();
        }

        try {
            DB::transaction(function () use ($user, $package, $order) {
                TbUser::updateSilk($user->jid, $package['type'], $package['value']);
                Donate::log(['method' => 'PayPal', 'transaction_id' => $order['id'], 'status' => 'success', 'amount' => $package['price'], 'type' => $package['type'], 'value' => $package['value'], 'jid' => $user->jid]);
            });
        } catch (\Throwable $e) {
            Error::log(['subject' => 'paypal callback', 'message' => 'Failed to complete payment: '.$e->getMessage().' | User ID: '.$user->jid.' | Order ID: '.$order['id'], 'jid' => $user->jid, 'ip' => request()->ip()]);
            throw $e;
        }

        return redirect()->route('account.donate')->with('success', number_format($package['value']).' '.__('Silk has been added to your account.'));
    }

    public function processStripe(Request $request)
    {
        $config = config('donate.stripe');

        $request->validate(['price' => 'required|numeric|min:0.01']);

        $user = Auth::user();

        $package = collect($config['package'])->firstWhere('price', $request->input('price'));
        if (! $package) {
            Error::log(['subject' => 'stripe process', 'message' => 'Invalid package selected. Requested price: '.$request->input('price'), 'jid' => $user?->jid, 'ip' => request()->ip()]);

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
            'metadata[jid]' => $user->jid,
            'metadata[package_price]' => $package['price'],
        ]);

        if (! $response->successful()) {
            Error::log(['subject' => 'stripe process', 'message' => 'Payment Failed: '.$this->apiError($response).' | Package: '.$package['name'].' ('.$package['price'].')', 'jid' => $user?->jid, 'ip' => request()->ip()]);

            return back()->withErrors(['stripe' => 'Payment Failed: '.($response->json('error.message') ?? 'An error occurred')])->withInput();
        }

        if (! $response->json('url') || ! $response->json('id')) {
            Error::log(['subject' => 'stripe process', 'message' => 'Payment Failed: Missing checkout URL/session ID. Session ID: '.($response->json('id') ?? 'unknown').' | Package: '.$package['name'].' ('.$package['price'].') | '.Str::limit($response->body(), 500), 'jid' => $user?->jid, 'ip' => request()->ip()]);

            return back()->withErrors(['stripe' => 'Payment Failed: An error occurred'])->withInput();
        }

        try {
            Donate::log(['method' => 'Stripe', 'transaction_id' => (string) $response->json('id'), 'status' => 'pending', 'amount' => $package['price'], 'type' => $package['type'], 'value' => $package['value'], 'jid' => $user->jid]);
        } catch (\Throwable $e) {
            Error::log(['subject' => 'stripe process', 'message' => 'Failed to store transaction: '.$e->getMessage().' | Session ID: '.$response->json('id').' | Package: '.$package['name'].' ('.$package['price'].')', 'jid' => $user->jid, 'ip' => request()->ip()]);
            throw $e;
        }

        return redirect()->away($response->json('url'));
    }

    public function callbackStripe(Request $request)
    {
        $status = $request->query('status');
        $sessionId = $request->query('session_id');
        $donate = $sessionId ? Donate::where('transaction_id', (string) $sessionId)->first() : null;

        if ($status === 'success') {
            return redirect()->route('account.donate')->with('success', __('Payment successful! Your silk will be added shortly.'));
        }

        Error::log(['subject' => 'stripe callback', 'message' => 'Stripe callback: payment cancelled or failed. Status: '.($status ?? 'missing').' | Session ID: '.($sessionId ?? 'unknown').' | Amount: '.($donate?->amount ?? 'unknown').' | Donation status: '.($donate?->status ?? 'unknown').' | User ID: '.($donate?->jid ?? Auth::user()?->jid ?? 'guest'), 'jid' => $donate?->jid ?? Auth::user()?->jid, 'ip' => request()->ip()]);

        return redirect()->route('account.donate')->withErrors(['stripe' => 'Payment was cancelled or failed. Please try again.']);
    }

    public function webhookStripe(Request $request)
    {
        $config = config('donate.stripe');
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        if (! $sigHeader) {
            Error::log(['subject' => 'stripe webhook', 'message' => 'Missing Stripe-Signature header | Payload: '.Str::limit($payload, 500), 'ip' => request()->ip()]);

            return response('Invalid signature', 400);
        }

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
            Error::log(['subject' => 'stripe webhook', 'message' => 'Invalid signature detected. Timestamp: '.($timestamp ?? 'missing').' | Payload: '.Str::limit($payload, 500), 'jid' => Auth::user()?->jid, 'ip' => request()->ip()]);

            return response('Invalid signature', 400);
        }

        $event = $request->json()->all();
        if (($event['type'] ?? '') !== 'checkout.session.completed') {
            Error::log(['subject' => 'stripe webhook', 'message' => 'Unexpected event type: '.($event['type'] ?? 'unknown').' | Event ID: '.($event['id'] ?? 'unknown'), 'jid' => Auth::user()?->jid, 'ip' => request()->ip()]);

            return response('OK', 200);
        }

        $session = $event['data']['object'];
        $sessionJid = isset($session['metadata']['jid']) ? (int) $session['metadata']['jid'] : null;
        if (($session['payment_status'] ?? '') !== 'paid') {
            Error::log(['subject' => 'stripe webhook', 'message' => 'Unexpected Payment Status: '.($session['payment_status'] ?? 'missing').' | Session ID: '.($session['id'] ?? 'unknown').' | Amount: '.($session['amount_total'] ?? 'unknown').' '.($session['currency'] ?? ''), 'jid' => $sessionJid, 'ip' => request()->ip()]);

            return response('OK', 200);
        }

        $donate = Donate::where('transaction_id', (string) ($session['id'] ?? ''))->where('status', 'pending')->first();
        if (! $donate) {
            Error::log(['subject' => 'stripe webhook', 'message' => 'Transaction not found or already processed. Session ID: '.($session['id'] ?? 'unknown').' | Amount: '.($session['amount_total'] ?? 'unknown').' '.($session['currency'] ?? ''), 'jid' => $sessionJid, 'ip' => request()->ip()]);

            return response('Transaction not found or already processed.', 409);
        }

        $package = collect($config['package'])->firstWhere('price', $donate->amount);
        if (! $package) {
            Error::log(['subject' => 'stripe webhook', 'message' => 'Invalid package price: '.$donate->amount.' | Session ID: '.($session['id'] ?? 'unknown'), 'jid' => $donate->jid, 'ip' => request()->ip()]);

            return response('Invalid package price', 422);
        }

        $user = User::where('jid', $donate->jid)->first();
        if (! $user) {
            Error::log(['subject' => 'stripe webhook', 'message' => 'User not found. User ID: '.$donate->jid.' | Session ID: '.($session['id'] ?? 'unknown'), 'jid' => $donate->jid, 'ip' => request()->ip()]);

            return response('User not found', 404);
        }

        try {
            DB::transaction(function () use ($user, $package, $donate) {
                TbUser::updateSilk($user->jid, $package['type'], $package['value']);
                $donate->update(['status' => 'success']);
            });
        } catch (\Throwable $e) {
            Error::log(['subject' => 'stripe webhook', 'message' => 'Failed to complete payment: '.$e->getMessage().' | User ID: '.$donate->jid.' | Session ID: '.($session['id'] ?? 'unknown'), 'jid' => $donate->jid, 'ip' => request()->ip()]);

            throw $e;
        }

        return response('OK', 200);
    }

    public function processNowpayments(Request $request)
    {
        $config = config('donate.nowpayments');

        $request->validate(['price' => 'required|numeric|min:1']);

        $user = Auth::user();

        $package = collect($config['package'])->firstWhere('price', $request->input('price'));
        if (! $package) {
            Error::log(['subject' => 'nowpayments process', 'message' => 'Invalid package selected. Requested price: '.$request->input('price'), 'jid' => $user?->jid, 'ip' => request()->ip()]);

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
            Error::log(['subject' => 'nowpayments process', 'message' => 'Payment Failed: '.$this->apiError($response).' | Package: '.$package['name'].' ('.$package['price'].')', 'jid' => $user?->jid, 'ip' => request()->ip()]);

            return back()->withErrors(['nowpayments' => 'Payment Failed: '.($response->json('message') ?? 'An error occurred')])->withInput();
        }

        if (! $response->json('invoice_url') || ! $response->json('id')) {
            Error::log(['subject' => 'nowpayments process', 'message' => 'Payment Failed: Missing invoice URL/ID. Invoice ID: '.($response->json('id') ?? 'unknown').' | Package: '.$package['name'].' ('.$package['price'].') | '.Str::limit($response->body(), 500), 'jid' => $user?->jid, 'ip' => request()->ip()]);

            return back()->withErrors(['nowpayments' => 'Payment Failed: An error occurred'])->withInput();
        }

        try {
            Donate::log(['method' => 'NOWPayments', 'transaction_id' => (string) $response->json('id'), 'status' => 'pending', 'amount' => $package['price'], 'type' => $package['type'], 'value' => $package['value'], 'jid' => $user->jid]);
        } catch (\Throwable $e) {
            Error::log(['subject' => 'nowpayments process', 'message' => 'Failed to store transaction: '.$e->getMessage().' | Invoice ID: '.$response->json('id').' | Package: '.$package['name'].' ('.$package['price'].')', 'jid' => $user->jid, 'ip' => request()->ip()]);
            throw $e;
        }

        return redirect()->away($response->json('invoice_url'));
    }

    public function callbackNowpayments(Request $request)
    {
        $status = $request->query('status');
        $invoiceId = $request->query('invoice_id');
        $donate = $invoiceId ? Donate::where('transaction_id', (string) $invoiceId)->first() : null;

        if ($status === 'success') {
            return redirect()->route('account.donate')->with('success', __('Payment successful! Your silk will be added shortly.'));
        }

        Error::log(['subject' => 'nowpayments callback', 'message' => 'NowPayments callback: payment failed or cancelled. Status: '.($status ?? 'missing').' | Invoice ID: '.($invoiceId ?? 'unknown').' | Amount: '.($donate?->amount ?? 'unknown').' | Donation status: '.($donate?->status ?? 'unknown').' | User ID: '.($donate?->jid ?? Auth::user()?->jid ?? 'guest'), 'jid' => $donate?->jid ?? Auth::user()?->jid, 'ip' => request()->ip()]);

        return redirect()->route('account.donate')->withErrors(['nowpayments' => 'Payment failed or was cancelled. Please try again.']);
    }

    public function webhookNowpayments(Request $request)
    {
        $config = config('donate.nowpayments');
        $data = $request->json()->all();

        if (! $data) {
            Error::log(['subject' => 'nowpayments webhook', 'message' => 'Invalid payload: '.Str::limit($request->getContent(), 500), 'jid' => Auth::user()?->jid, 'ip' => request()->ip()]);

            return response('Invalid payload', 400);
        }

        $received = $request->header('x-nowpayments-sig');
        if (! $received) {
            Error::log(['subject' => 'nowpayments webhook', 'message' => 'Missing x-nowpayments-sig header | Invoice ID: '.($data['invoice_id'] ?? 'unknown'), 'ip' => request()->ip()]);

            return response('Invalid signature', 400);
        }

        $sorted = $data;
        ksort($sorted);
        $hash = hash_hmac('sha512', json_encode($sorted, JSON_UNESCAPED_UNICODE), $config['ipn_secret']);
        if (! hash_equals($hash, $received)) {
            Error::log(['subject' => 'nowpayments webhook', 'message' => 'Invalid signature detected | Invoice ID: '.($data['invoice_id'] ?? 'unknown'), 'jid' => Auth::user()?->jid, 'ip' => request()->ip()]);

            return response('Invalid signature', 400);
        }

        if (($data['payment_status'] ?? '') !== 'finished') {
            Error::log(['subject' => 'nowpayments webhook', 'message' => 'Wrong payment status: '.($data['payment_status'] ?? 'missing').' | Invoice ID: '.($data['invoice_id'] ?? 'unknown').' | Payment ID: '.($data['payment_id'] ?? 'unknown').' | Amount: '.($data['price_amount'] ?? 'unknown'), 'jid' => Auth::user()?->jid, 'ip' => request()->ip()]);

            return response('OK', 200);
        }

        $donate = Donate::where('transaction_id', (string) ($data['invoice_id'] ?? ''))->where('status', 'pending')->first();
        if (! $donate) {
            Error::log(['subject' => 'nowpayments webhook', 'message' => 'Transaction not found or already processed. Invoice ID: '.($data['invoice_id'] ?? 'unknown').' | Payment ID: '.($data['payment_id'] ?? 'unknown').' | Amount: '.($data['price_amount'] ?? 'unknown'), 'jid' => Auth::user()?->jid, 'ip' => request()->ip()]);

            return response('Transaction not found or already processed.', 409);
        }

        $package = collect($config['package'])->firstWhere('price', $donate->amount);
        if (! $package) {
            Error::log(['subject' => 'nowpayments webhook', 'message' => 'Invalid package price: '.$donate->amount.' | Invoice ID: '.($data['invoice_id'] ?? 'unknown'), 'jid' => $donate->jid, 'ip' => request()->ip()]);

            return response('Invalid package price', 422);
        }

        $user = User::where('jid', $donate->jid)->first();
        if (! $user) {
            Error::log(['subject' => 'nowpayments webhook', 'message' => 'User not found. User ID: '.$donate->jid.' | Invoice ID: '.($data['invoice_id'] ?? 'unknown'), 'jid' => $donate->jid, 'ip' => request()->ip()]);

            return response('User not found', 404);
        }

        try {
            DB::transaction(function () use ($user, $package, $donate) {
                TbUser::updateSilk($user->jid, $package['type'], $package['value']);
                $donate->update(['status' => 'success']);
            });
        } catch (\Throwable $e) {
            Error::log(['subject' => 'nowpayments webhook', 'message' => 'Failed to complete payment: '.$e->getMessage().' | User ID: '.$donate->jid.' | Invoice ID: '.($data['invoice_id'] ?? 'unknown'), 'jid' => $donate->jid, 'ip' => request()->ip()]);

            throw $e;
        }

        return response('OK', 200);
    }

    public function processFawaterk(Request $request)
    {
        $config = config('donate.fawaterk');

        $request->validate(['price' => 'required|numeric|min:5']);

        $user = Auth::user();

        $package = collect($config['package'])->firstWhere('price', $request->input('price'));
        if (! $package) {
            Error::log(['subject' => 'fawaterk process', 'message' => 'Invalid package selected. Requested price: '.$request->input('price'), 'jid' => $user?->jid, 'ip' => request()->ip()]);

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
            $errorMessage = is_array($response->json('message')) ? collect($response->json('message'))->flatten()->first() : $response->json('message');
            $errorMessage = $errorMessage ?: 'An error occurred';

            Error::log(['subject' => 'fawaterk process', 'message' => 'Payment Failed: '.$errorMessage.' | API: '.$this->apiError($response).' | Package: '.$package['name'].' ('.$package['price'].')', 'jid' => $user?->jid, 'ip' => request()->ip()]);

            return back()->withErrors(['fawaterk' => 'Payment Failed: '.$errorMessage])->withInput();
        }

        if (! $response->json('data.url') || ! $response->json('data.invoiceId')) {
            Error::log(['subject' => 'fawaterk process', 'message' => 'Payment Failed: Missing payment URL/invoice ID. Invoice ID: '.($response->json('data.invoiceId') ?? 'unknown').' | Package: '.$package['name'].' ('.$package['price'].') | '.Str::limit($response->body(), 500), 'jid' => $user?->jid, 'ip' => request()->ip()]);

            return back()->withErrors(['fawaterk' => 'Payment Failed: An error occurred'])->withInput();
        }

        $existing = Donate::where('transaction_id', (string) $response->json('data.invoiceId'))->first();
        if ($existing && $existing->status === 'success') {
            Error::log(['subject' => 'fawaterk process', 'message' => 'This invoice was already paid. Invoice ID: '.$response->json('data.invoiceId').' | User ID: '.$user->jid, 'jid' => $user->jid, 'ip' => request()->ip()]);

            return back()->withErrors(['fawaterk' => 'This invoice was already paid.'])->withInput();
        }

        try {
            Donate::updateOrCreate(['transaction_id' => (string) $response->json('data.invoiceId')], [
                'method' => 'Fawaterk',
                'status' => 'pending',
                'amount' => $package['price'],
                'type' => $package['type'],
                'value' => $package['value'],
                'desc' => '',
                'jid' => $user->jid,
                'ip' => request()->ip(),
            ]);
        } catch (\Throwable $e) {
            Error::log(['subject' => 'fawaterk process', 'message' => 'Failed to store transaction: '.$e->getMessage().' | Invoice ID: '.$response->json('data.invoiceId').' | User ID: '.$user->jid, 'jid' => $user->jid, 'ip' => request()->ip()]);
            throw $e;
        }

        return redirect()->away($response->json('data.url'));
    }

    public function callbackFawaterk(Request $request)
    {
        $status = $request->query('status');
        $invoiceId = $request->query('invoice_id');
        $donate = $invoiceId ? Donate::where('transaction_id', (string) $invoiceId)->first() : null;

        if ($status === 'success') {
            return redirect()->route('account.donate')->with('success', __('Payment successful! Your silk will be added shortly.'));
        }

        if ($status === 'fail') {
            Error::log(['subject' => 'fawaterk callback', 'message' => 'Fawaterk callback: payment failed | Invoice ID: '.($invoiceId ?? 'unknown').' | Amount: '.($donate?->amount ?? 'unknown').' | Donation status: '.($donate?->status ?? 'unknown').' | User ID: '.($donate?->jid ?? Auth::user()?->jid ?? 'guest'), 'jid' => $donate?->jid ?? Auth::user()?->jid, 'ip' => request()->ip()]);

            return redirect()->route('account.donate')->withErrors(['fawaterk' => 'Payment failed. Please try again.']);
        }

        if ($status === 'pending') {
            Error::log(['subject' => 'fawaterk callback', 'message' => 'Fawaterk callback: payment pending | Invoice ID: '.($invoiceId ?? 'unknown').' | Amount: '.($donate?->amount ?? 'unknown').' | Donation status: '.($donate?->status ?? 'unknown').' | User ID: '.($donate?->jid ?? Auth::user()?->jid ?? 'guest'), 'jid' => $donate?->jid ?? Auth::user()?->jid, 'ip' => request()->ip()]);

            return redirect()->route('account.donate')->with('info', __('Payment is pending. Your silk will be added once confirmed.'));
        }

        Error::log(['subject' => 'fawaterk callback', 'message' => 'Fawaterk callback: unknown status ('.($status ?? 'missing').') | Invoice ID: '.($invoiceId ?? 'unknown').' | Amount: '.($donate?->amount ?? 'unknown').' | Donation status: '.($donate?->status ?? 'unknown').' | User ID: '.($donate?->jid ?? Auth::user()?->jid ?? 'guest'), 'jid' => $donate?->jid ?? Auth::user()?->jid, 'ip' => request()->ip()]);

        return redirect()->route('account.donate')->withErrors(['fawaterk' => 'Unknown payment status.']);
    }

    public function webhookFawaterk(Request $request)
    {
        $config = config('donate.fawaterk');
        $data = $request->all();

        if (! $data) {
            Error::log(['subject' => 'fawaterk webhook', 'message' => 'Invalid payload: '.Str::limit($request->getContent(), 500), 'ip' => request()->ip()]);

            return response('Invalid payload', 400);
        }

        $hash = hash_hmac('sha256', 'InvoiceId='.($data['invoice_id'] ?? '').'&InvoiceKey='.($data['invoice_key'] ?? '').'&PaymentMethod='.($data['payment_method'] ?? ''), $config['provider_key']);
        if (! isset($data['hashKey']) || ! hash_equals($hash, (string) $data['hashKey'])) {
            Error::log(['subject' => 'fawaterk webhook', 'message' => 'Invalid hash detected for invoice: '.($data['invoice_id'] ?? 'unknown').' | Payment method: '.($data['payment_method'] ?? 'unknown').' | hashKey present: '.(isset($data['hashKey']) ? 'yes' : 'no'), 'ip' => request()->ip()]);

            return response('Invalid Hash', 400);
        }

        if (($data['invoice_status'] ?? '') !== 'paid') {
            Error::log(['subject' => 'fawaterk webhook', 'message' => 'Unexpected invoice status: '.($data['invoice_status'] ?? 'missing').' | Invoice ID: '.($data['invoice_id'] ?? 'unknown'), 'jid' => null, 'ip' => request()->ip()]);

            return response('Not paid', 200);
        }

        $donate = Donate::where('transaction_id', (string) ($data['invoice_id'] ?? ''))->first();
        if (! $donate || $donate->status !== 'pending') {
            Error::log(['subject' => 'fawaterk webhook', 'message' => 'Transaction not found or already processed. Invoice ID: '.($data['invoice_id'] ?? 'unknown').' | Donation status: '.($donate?->status ?? 'missing'), 'jid' => $donate?->jid, 'ip' => request()->ip()]);

            return response('Transaction not found or already processed.', 409);
        }

        $package = collect($config['package'])->firstWhere('price', $donate->amount);
        if (! $package) {
            Error::log(['subject' => 'fawaterk webhook', 'message' => 'Invalid package price: '.$donate->amount.' | Invoice ID: '.($data['invoice_id'] ?? 'unknown'), 'jid' => $donate->jid, 'ip' => request()->ip()]);

            return response('Invalid package price', 422);
        }

        $user = User::where('jid', $donate->jid)->first();
        if (! $user) {
            Error::log(['subject' => 'fawaterk webhook', 'message' => 'User not found. User ID: '.$donate->jid.' | Invoice ID: '.($data['invoice_id'] ?? 'unknown'), 'jid' => $donate->jid, 'ip' => request()->ip()]);

            return response('User not found', 404);
        }

        try {
            DB::transaction(function () use ($user, $package, $donate) {
                TbUser::updateSilk($user->jid, $package['type'], $package['value']);
                $donate->update(['status' => 'success']);
            });
        } catch (\Throwable $e) {
            Error::log(['subject' => 'fawaterk webhook', 'message' => 'Failed to complete payment: '.$e->getMessage().' | User ID: '.$donate->jid.' | Invoice ID: '.($data['invoice_id'] ?? 'unknown'), 'jid' => $donate->jid, 'ip' => request()->ip()]);

            throw $e;
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
            Error::log(['subject' => 'maxicard process', 'message' => 'Payment Failed: '.$this->apiError($response), 'jid' => $user->jid, 'ip' => request()->ip()]);

            return back()->withErrors(['maxicard' => 'Payment Failed: An error occurred'])->withInput();
        }

        $xml = simplexml_load_string($response->body());
        if ($xml === false) {
            Error::log(['subject' => 'maxicard process', 'message' => 'Invalid response from payment provider: '.Str::limit($response->body(), 500), 'jid' => $user->jid, 'ip' => request()->ip()]);

            return back()->withErrors(['maxicard' => 'Invalid response from payment provider.'])->withInput();
        }

        if (trim((string) $xml->params->durum) !== 'ok' || intval(trim((string) $xml->params->siparis_no)) <= 0) {
            $providerMessage = trim((string) $xml->params->aciklama);
            Error::log(['subject' => 'maxicard process', 'message' => 'Payment Failed: '.$providerMessage.' | durum: '.trim((string) $xml->params->durum).' | Order ID: '.trim((string) $xml->params->siparis_no).' | Amount: '.trim((string) $xml->params->tutar), 'jid' => $user->jid, 'ip' => request()->ip()]);

            return back()->withErrors(['maxicard' => 'Payment Failed: '.$providerMessage])->withInput();
        }

        $providerAmount = intval(preg_replace('/[^0-9]/', '', (string) $xml->params->tutar));
        $package = collect($config['package'])->firstWhere('price', $providerAmount);
        if (! $package) {
            Error::log(['subject' => 'maxicard process', 'message' => 'Invalid package price. Provider amount: '.$providerAmount.' | Raw amount: '.trim((string) $xml->params->tutar).' | Order ID: '.trim((string) $xml->params->siparis_no), 'jid' => $user->jid, 'ip' => request()->ip()]);

            return back()->withErrors(['maxicard' => 'Invalid package price.'])->withInput();
        }

        $providerOrderId = intval(trim((string) $xml->params->siparis_no));

        try {
            DB::transaction(function () use ($user, $package, $providerOrderId) {
                TbUser::updateSilk($user->jid, $package['type'], $package['value']);
                Donate::log(['method' => 'MaxiCard', 'transaction_id' => $providerOrderId, 'status' => 'success', 'amount' => $package['price'], 'type' => $package['type'], 'value' => $package['value'], 'jid' => $user->jid]);
            });
        } catch (\Illuminate\Database\QueryException $e) {
            if ((string) $e->getCode() === '23000') {
                Error::log(['subject' => 'maxicard process', 'message' => 'Payment already processed (duplicate e-PIN). Order ID: '.$providerOrderId.' | User ID: '.$user->jid, 'jid' => $user->jid, 'ip' => request()->ip()]);

                return redirect()->route('account.donate')->with('success', __('Payment already processed.'));
            }

            Error::log(['subject' => 'maxicard process', 'message' => 'Failed to store transaction: '.$e->getMessage().' | Order ID: '.$providerOrderId.' | User ID: '.$user->jid, 'jid' => $user->jid, 'ip' => request()->ip()]);
            throw $e;
        } catch (\Throwable $e) {
            Error::log(['subject' => 'maxicard process', 'message' => 'Failed to complete payment: '.$e->getMessage().' | Order ID: '.$providerOrderId.' | User ID: '.$user->jid, 'jid' => $user->jid, 'ip' => request()->ip()]);
            throw $e;
        }

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
            Error::log(['subject' => 'hipocard process', 'message' => 'Payment Failed: '.$this->apiError($response), 'jid' => $user->jid, 'ip' => request()->ip()]);

            return back()->withErrors(['hipocard' => 'Payment Failed: '.($response->json('message') ?? 'An error occurred')])->withInput();
        }

        $providerAmount = intval($response->json('data.total_sales'));
        $package = collect($config['package'])->firstWhere('price', $providerAmount);
        if (! $package) {
            Error::log(['subject' => 'hipocard process', 'message' => 'Invalid package price. Provider amount: '.$providerAmount.' | Raw amount: '.($response->json('data.total_sales') ?? 'unknown').' | User ID: '.$user->jid, 'jid' => $user->jid, 'ip' => request()->ip()]);

            return back()->withErrors(['hipocard' => 'Invalid package price.'])->withInput();
        }

        $transactionId = 'hipocard-'.substr(hash('sha256', 'hipocard|'.trim($request->code)), 0, 32);

        try {
            DB::transaction(function () use ($user, $package, $transactionId) {
                TbUser::updateSilk($user->jid, $package['type'], $package['value']);
                Donate::log(['method' => 'HipoCard', 'transaction_id' => $transactionId, 'status' => 'success', 'amount' => $package['price'], 'type' => $package['type'], 'value' => $package['value'], 'jid' => $user->jid]);
            });
        } catch (\Illuminate\Database\QueryException $e) {
            if ((string) $e->getCode() === '23000') {
                Error::log(['subject' => 'hipocard process', 'message' => 'Payment already processed (duplicate e-PIN). Transaction ID: '.$transactionId.' | User ID: '.$user->jid, 'jid' => $user->jid, 'ip' => request()->ip()]);

                return redirect()->route('account.donate')->with('success', __('Payment already processed.'));
            }

            Error::log(['subject' => 'hipocard process', 'message' => 'Failed to store transaction: '.$e->getMessage().' | Transaction ID: '.$transactionId.' | User ID: '.$user->jid, 'jid' => $user->jid, 'ip' => request()->ip()]);
            throw $e;
        } catch (\Throwable $e) {
            Error::log(['subject' => 'hipocard process', 'message' => 'Failed to complete payment: '.$e->getMessage().' | Transaction ID: '.$transactionId.' | User ID: '.$user->jid, 'jid' => $user->jid, 'ip' => request()->ip()]);
            throw $e;
        }

        return redirect()->route('account.donate')->with('success', number_format($package['value']).' '.__('Silk has been added to your account.'));
    }

    public function processHipopay(Request $request)
    {
        $config = config('donate.hipopay');

        $request->validate(['price' => 'required|numeric|min:0.01']);

        $user = Auth::user();

        $package = collect($config['package'])->firstWhere('price', $request->input('price'));
        if (! $package) {
            Error::log(['subject' => 'hipopay process', 'message' => 'Invalid package selected. Requested price: '.$request->input('price'), 'jid' => $user?->jid, 'ip' => request()->ip()]);

            return back()->withErrors(['hipopay' => 'Invalid package selected.'])->withInput();
        }

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

        if (! $response->successful() || $response->json('success') !== true) {
            Error::log(['subject' => 'hipopay process', 'message' => 'Payment Failed: '.$this->apiError($response).' | Package: '.$package['name'].' ('.$package['price'].')', 'jid' => $user->jid, 'ip' => request()->ip()]);

            return back()->withErrors(['hipopay' => 'Payment Failed: '.($response->json('message') ?? 'An error occurred')])->withInput();
        }

        if (! $response->json('data.payment_url')) {
            Error::log(['subject' => 'hipopay process', 'message' => 'Payment Failed: Missing payment URL. | API: '.$this->apiError($response).' | Package: '.$package['name'].' ('.$package['price'].')', 'jid' => $user->jid, 'ip' => request()->ip()]);

            return back()->withErrors(['hipopay' => 'Payment Failed: An error occurred'])->withInput();
        }

        try {
            Donate::log(['method' => 'HipoPay', 'status' => 'pending', 'amount' => $package['price'], 'type' => $package['type'], 'value' => $package['value'], 'jid' => $user->jid]);
        } catch (\Throwable $e) {
            Error::log(['subject' => 'hipopay process', 'message' => 'Failed to store transaction: '.$e->getMessage().' | User ID: '.$user->jid.' | Package: '.$package['name'].' ('.$package['price'].')', 'jid' => $user->jid, 'ip' => request()->ip()]);
            throw $e;
        }

        return redirect()->away($response->json('data.payment_url'));
    }

    public function webhookHipopay(Request $request)
    {
        $config = config('donate.hipopay');
        $data = $request->json()->all();

        if (! $data) {
            Error::log(['subject' => 'hipopay webhook', 'message' => 'Invalid payload: '.Str::limit($request->getContent(), 500), 'ip' => request()->ip()]);

            return response('Invalid payload', 400);
        }

        $hash = base64_encode(hash_hmac('sha256', ($data['transaction_id'] ?? '').($data['user_id'] ?? '').($data['email'] ?? '').($data['name'] ?? '').($data['status'] ?? '').$config['api_key'], $config['api_password'], true));
        if (! isset($data['hash']) || ! hash_equals($hash, (string) $data['hash'])) {
            Error::log(['subject' => 'hipopay webhook', 'message' => 'Invalid hash detected. Transaction ID: '.($data['transaction_id'] ?? 'unknown').' | User ID: '.($data['user_id'] ?? 'missing').' | hash present: '.(isset($data['hash']) ? 'yes' : 'no'), 'ip' => request()->ip()]);

            return response('Invalid Hash', 400);
        }

        if (($data['status'] ?? '') !== 'success') {
            Error::log(['subject' => 'hipopay webhook', 'message' => 'Payment not successful: '.($data['status'] ?? 'missing').' | Transaction ID: '.($data['transaction_id'] ?? 'unknown').' | User ID: '.($data['user_id'] ?? 'missing').' | Amount: '.($data['payment_total'] ?? 'unknown'), 'ip' => request()->ip()]);

            return response('Payment not successful', 422);
        }

        $user = User::where('jid', $data['user_id'] ?? null)->first();
        if (! $user) {
            Error::log(['subject' => 'hipopay webhook', 'message' => 'User not found. User ID: '.($data['user_id'] ?? 'missing').' | Transaction ID: '.($data['transaction_id'] ?? 'unknown'), 'jid' => is_numeric($data['user_id'] ?? null) ? (int) $data['user_id'] : null, 'ip' => request()->ip()]);

            return response('User not found', 404);
        }

        $donate = Donate::where('method', 'HipoPay')->where('jid', $user->jid)->where('status', 'pending')->where('amount', intval(($data['payment_total'] ?? 0) / 100))->latest('id')->first();
        if (! $donate) {
            if (Donate::where('transaction_id', (string) ($data['transaction_id'] ?? ''))->where('status', 'success')->exists()) {
                Error::log(['subject' => 'hipopay webhook', 'message' => 'Transaction already processed. Transaction ID: '.($data['transaction_id'] ?? 'unknown').' | User ID: '.$user->jid, 'jid' => $user->jid, 'ip' => request()->ip()]);

                return response('Transaction already processed.', 409);
            }

            Error::log(['subject' => 'hipopay webhook', 'message' => 'No matching pending transaction. User ID: '.$user->jid.' | Amount: '.($data['payment_total'] ?? 'missing').' | Transaction ID: '.($data['transaction_id'] ?? 'unknown'), 'jid' => $user->jid, 'ip' => request()->ip()]);

            return response('No matching pending transaction.', 422);
        }

        $package = collect($config['package'])->firstWhere('price', (int) $donate->amount);
        if (! $package) {
            Error::log(['subject' => 'hipopay webhook', 'message' => 'Invalid package price: '.$donate->amount.' | Transaction ID: '.($data['transaction_id'] ?? 'unknown').' | User ID: '.$user->jid, 'jid' => $user->jid, 'ip' => request()->ip()]);

            return response('Invalid package price', 422);
        }

        try {
            DB::transaction(function () use ($user, $package, $donate, $data) {
                TbUser::updateSilk($user->jid, $package['type'], $package['value']);
                $donate->update(['status' => 'success', 'transaction_id' => (string) ($data['transaction_id'] ?? ''),]);
            });
        } catch (\Throwable $e) {
            Error::log(['subject' => 'hipopay webhook', 'message' => 'Failed to complete payment: '.$e->getMessage().' | Transaction ID: '.($data['transaction_id'] ?? 'unknown').' | User ID: '.$user->jid, 'jid' => $user->jid, 'ip' => request()->ip()]);

            throw $e;
        }

        return response('OK', 200);
    }

    /**
     * Build a compact, human-readable error string from a provider response.
     * Includes HTTP status, the provider message and a truncated raw body so
     * failed donations can be diagnosed from the admin error log.
     */
    private function apiError($response): string
    {
        $message = $response->json('message') ?? $response->json('error_description') ?? $response->json('error.message') ?? $response->json('error');

        if (is_array($message)) {
            $message = collect($message)->flatten()->first();
        }

        $body = trim((string) $response->body());

        return trim(
            'HTTP '.$response->status().' - '.($message ?: 'No error message').($body !== '' ? ' - '.Str::limit($body, 500) : '')
        );
    }
}
