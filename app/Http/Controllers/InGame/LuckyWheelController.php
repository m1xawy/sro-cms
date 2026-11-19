<?php

namespace App\Http\Controllers\InGame;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LuckyWheelController extends Controller
{
    public function index(Request $request)
    {
        return view('ingame.lucky-wheel.index');
    }
}
