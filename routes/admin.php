<?php

use App\Http\Controllers\Admin\CharacterController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DownloadController;
use App\Http\Controllers\Admin\LogController;
use App\Http\Controllers\Admin\NewsController;
use App\Http\Controllers\Admin\PagesController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TicketController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VoucherController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/admin/index', [DashboardController::class, 'index'])->name('admin');
    Route::get('/admin/worldmap', [DashboardController::class, 'worldmap'])->name('admin.worldmap');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/logs/donate', [LogController::class, 'donate'])->name('logs.donate');
        Route::get('/logs/referral', [LogController::class, 'referral'])->name('logs.referral');
        Route::get('/logs/vote', [LogController::class, 'vote'])->name('logs.vote');
        Route::get('/logs/smc', [LogController::class, 'smc'])->name('logs.smc');

        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::get('/general', [SettingController::class, 'general'])->name('settings.general');
        Route::get('/widgets', [SettingController::class, 'widgets'])->name('settings.widgets');
        Route::get('/donate', [SettingController::class, 'donate'])->name('settings.donate');
        Route::get('/ranking', [SettingController::class, 'ranking'])->name('settings.ranking');
        Route::get('/webapps', [SettingController::class, 'webApps'])->name('settings.webapps');
        Route::get('/item-lookup', [SettingController::class, 'itemLookup'])->name('settings.item-lookup');
        Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');
        Route::post('/settings/clear-cache', [SettingController::class, 'clearCache'])->name('settings.clear-cache');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}/view', [UserController::class, 'view'])->name('users.view');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/silk', [UserController::class, 'addSilk'])->name('users.add-silk');
        Route::post('/users/{user}/block', [UserController::class, 'block'])->name('users.block');
        Route::post('/users/{user}/unblock', [UserController::class, 'unblock'])->name('users.unblock');
        Route::post('/users/{user}/change-password', [UserController::class, 'changePassword'])->name('users.change-password');
        Route::post('/users/{user}/change-email', [UserController::class, 'changeEmail'])->name('users.change-email');

        Route::get('/characters', [CharacterController::class, 'index'])->name('characters.index');
        Route::get('/characters/{char}/view', [CharacterController::class, 'view'])->name('characters.view');
        Route::put('/characters/{char}', [CharacterController::class, 'update'])->name('characters.update');
        Route::put('/characters/{char}/unstuck', [CharacterController::class, 'unstuck'])->name('characters.unstuck');
        Route::post('/characters/{char}/add-item', [CharacterController::class, 'addItem'])->name('characters.add-item');

        Route::get('/news', [NewsController::class, 'index'])->name('news.index');
        Route::get('/news/create', [NewsController::class, 'create'])->name('news.create');
        Route::post('/news', [NewsController::class, 'store'])->name('news.store');
        Route::get('/news/{news}/edit', [NewsController::class, 'edit'])->name('news.edit');
        Route::put('/news/{news}', [NewsController::class, 'update'])->name('news.update');
        Route::get('/news/{news}/delete', [NewsController::class, 'confirmDelete'])->name('news.delete');
        Route::delete('/news/{news}', [NewsController::class, 'destroy'])->name('news.destroy');

        Route::get('/download', [DownloadController::class, 'index'])->name('download.index');
        Route::get('/download/create', [DownloadController::class, 'create'])->name('download.create');
        Route::post('/download', [DownloadController::class, 'store'])->name('download.store');
        Route::get('/download/{download}/edit', [DownloadController::class, 'edit'])->name('download.edit');
        Route::put('/download/{download}', [DownloadController::class, 'update'])->name('download.update');
        Route::get('/download/{download}/delete', [DownloadController::class, 'confirmDelete'])->name('download.delete');
        Route::delete('/download/{download}', [DownloadController::class, 'destroy'])->name('download.destroy');

        Route::get('/pages', [PagesController::class, 'index'])->name('pages.index');
        Route::get('/pages/create', [PagesController::class, 'create'])->name('pages.create');
        Route::post('/pages', [PagesController::class, 'store'])->name('pages.store');
        Route::get('/pages/{pages}/edit', [PagesController::class, 'edit'])->name('pages.edit');
        Route::put('/pages/{pages}', [PagesController::class, 'update'])->name('pages.update');
        Route::get('/pages/{pages}/delete', [PagesController::class, 'confirmDelete'])->name('pages.delete');
        Route::delete('/pages/{pages}', [PagesController::class, 'destroy'])->name('pages.destroy');

        Route::get('vouchers', [VoucherController::class, 'index'])->name('vouchers.index');
        Route::get('/vouchers/create', [VoucherController::class, 'create'])->name('vouchers.create');
        Route::post('vouchers', [VoucherController::class, 'store'])->name('vouchers.store');
        Route::post('/vouchers/{voucher}/toggle', [VoucherController::class, 'toggle'])->name('vouchers.toggle');

        Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
        Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('ticket.show');
        Route::post('/tickets/{ticket}/reply', [TicketController::class, 'reply'])->name('ticket.reply');
        Route::post('/tickets/{ticket}/close', [TicketController::class, 'close'])->name('ticket.close');
    });
});
