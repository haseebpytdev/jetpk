<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\SecurityEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Dev CP security events log viewer.
 */
class DevCpSecurityEventsController extends Controller
{
    public function index(Request $request): View
    {
        $query = SecurityEvent::query()->orderByDesc('created_at');

        if ($request->filled('event_type')) {
            $query->where('event_type', $request->string('event_type'));
        }

        if ($request->filled('outcome')) {
            $query->where('outcome', $request->string('outcome'));
        }

        return view('developer.security-events.index', [
            'events' => $query->paginate(30)->withQueryString(),
            'filters' => $request->only(['event_type', 'outcome']),
        ]);
    }
}
