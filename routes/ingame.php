<?php

use App\Http\Controllers\InGame\BannerController;
use App\Http\Controllers\InGame\BattlePassController;
use App\Http\Controllers\InGame\FortressController;
use App\Http\Controllers\InGame\LuckyWheelController;
use App\Http\Controllers\InGame\RankingController;
use App\Http\Controllers\InGame\SurveyController;
use App\Http\Controllers\InGame\WebmallController;
use Illuminate\Support\Facades\Route;

Route::prefix('in-game')->middleware('nocache')->name('in-game.')->group(function() {
    Route::any('/webmall', [WebmallController::class, 'webmall'])->name('webmall');
    Route::any('/ranking', [RankingController::class, 'ranking'])->name('ranking');
    Route::any('/survey', [SurveyController::class, 'survey'])->name('survey');
    Route::any('/fortress', [FortressController::class, 'fortress'])->name('fortress');
    Route::any('/banner', [BannerController::class, 'banner'])->name('banner');
    Route::any('/lucky-wheel', [LuckyWheelController::class, 'index'])->name('luckywheel');

    Route::any('/battle-pass', [BattlePassController::class, 'index'])->name('battlepass');
    Route::prefix('battlepass')->name('battlepass.')->group(function () {
        Route::get('/tiers', [BattlePassController::class, 'tiers'])->middleware('throttle:60,1')->name('tiers');
        Route::post('/claim', [BattlePassController::class, 'claim'])->middleware('throttle:10,1')->name('claim');
        Route::post('/purchase-premium', [BattlePassController::class, 'purchasePremium'])->middleware('throttle:10,1')->name('purchase-premium');
        Route::post('/purchase-points', [BattlePassController::class, 'purchasePoints'])->middleware('throttle:10,1')->name('purchase-points');
        Route::post('/add-points', [BattlePassController::class, 'addPoints'])->middleware('throttle:10,1')->name('add-points');
        Route::get('/silk', [BattlePassController::class, 'getSilk'])->middleware('throttle:60,1')->name('silk');
    });
});
