<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\PagesController;
use App\Http\Controllers\API\RankingController;
use Illuminate\Support\Facades\Route;

Route::get('/ranking/player', [RankingController::class, 'player']);
Route::get('/ranking/guild', [RankingController::class, 'guild']);
Route::get('/ranking/unique', [RankingController::class, 'unique']);
Route::get('/ranking/level', [RankingController::class, 'level']);
Route::get('/ranking/fortress-player', [RankingController::class, 'fortress_player']);
Route::get('/ranking/fortress-guild', [RankingController::class, 'fortress_guild']);
Route::get('/ranking/character/{char}', [RankingController::class, 'characterView']);
Route::get('/ranking/guild/{guild}', [RankingController::class, 'guildView']);

Route::get('/news', [PagesController::class, 'news']);
Route::get('/download', [PagesController::class, 'download']);
Route::get('/schedule', [PagesController::class, 'schedule']);
Route::get('/uniques', [PagesController::class, 'uniques']);

Route::get('/online-counter', [PagesController::class, 'onlineCounter']);

Route::middleware(['web', 'throttle:5,1'])->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgot_password']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [AuthController::class, 'profile'])->middleware(config('global.register_confirm') ? 'verified' : []);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/login/verify', [AuthController::class, 'verify'])->middleware('throttle:10,1');
    Route::post('/login/verify/resend', [AuthController::class, 'resendVerify'])->middleware('throttle:5,1');
});
