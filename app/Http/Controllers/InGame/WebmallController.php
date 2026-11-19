<?php

namespace App\Http\Controllers\InGame;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WebmallController extends Controller
{
    public function webmall(Request $request)
    {
        return view('ingame.webmall.index');
    }
}
