<?php

use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Account\ReferralController;
use App\Http\Controllers\Account\TicketController;
use App\Http\Controllers\Account\VoteController;
use App\Http\Controllers\Account\VoucherController;
use App\Http\Controllers\Account\DonateController;
use Illuminate\Support\Facades\Route;

Route::middleware(array_filter(['auth', config('global.register_confirm') ? 'verified' : null]))->group(function () {
    Route::get('/account', [ProfileController::class, 'index'])->name('account');

    Route::prefix('account')->name('account.')->group(function() {
        Route::get('/edit', [ProfileController::class, 'edit'])->name('edit');
        Route::patch('/edit', [ProfileController::class, 'update'])->middleware('throttle:10,1')->name('update');
        Route::delete('/edit', [ProfileController::class, 'destroy'])->name('destroy');

        Route::post('/edit/settings', [ProfileController::class, 'updateSettings'])->middleware('throttle:20,1')->name('settings.update');
        Route::post('/edit/send-verify-code', [ProfileController::class, 'sendVerifyCode'])->middleware('throttle:5,1')->name('resend.verify.code');
        Route::post('/edit/reset-secondary-password', [ProfileController::class, 'secondaryPasswordReset'])->middleware('throttle:10,1')->name('reset.secondary.password');

        Route::get('/donate', [DonateController::class, 'index'])->name('donate');
        Route::get('/donate/history', [DonateController::class, 'history'])->name('donate.history');
        Route::get('/donate/{method}', [DonateController::class, 'show'])->name('donate.show');
        Route::post('/donate/{method}/process', [DonateController::class, 'process'])->middleware('throttle:5,1')->name('donate.process');

        Route::get('/tickets', [TicketController::class, 'index'])->name('tickets');
        Route::get('/tickets/create', [TicketController::class, 'create'])->name('ticket.create');
        Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('ticket.show');
        Route::post('/tickets/send', [TicketController::class, 'send'])->middleware('throttle:10,1')->name('ticket.send');

        Route::get('/voucher', [VoucherController::class, 'index'])->name('voucher');
        Route::post('/voucher/redeem', [VoucherController::class, 'redeem'])->middleware('throttle:10,1')->name('voucher.redeem');

        Route::get('/referral', [ReferralController::class, 'index'])->name('referral');
        Route::post('/referral-redeem', [ReferralController::class, 'redeem'])->middleware('throttle:10,1')->name('referral.redeem');

        Route::get('/vote', [VoteController::class, 'index'])->name('vote');
        Route::get('/vote/{id}', [VoteController::class, 'voting'])->name('vote.voting');
    });
});
