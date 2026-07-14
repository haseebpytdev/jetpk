<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Developer / product-owner control panel landing (deployment tools, not client admin).
 */
class DeveloperControlPanelController extends Controller
{
    public function index(): View
    {
        return view('developer.control-panel.index');
    }
}
