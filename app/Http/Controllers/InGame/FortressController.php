<?php

namespace App\Http\Controllers\InGame;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class FortressController extends Controller
{
    public function fortress(Request $request)
    {
        return view('ingame.fortress.index');
    }
}
