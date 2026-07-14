<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Support\Ui\UiVersionAuditService;
use Illuminate\Contracts\View\View;

/**
 * Dev CP read-only UI version channel visibility.
 */
class DevCpUiVersionsController extends Controller
{
    public function __construct(
        protected UiVersionAuditService $audit,
    ) {}

    public function index(): View
    {
        return view('developer.monitoring.ui-versions', [
            'snapshot' => $this->audit->snapshot(),
        ]);
    }
}
