<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class RequestDemoController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        return redirect()->route('support');
    }
}
