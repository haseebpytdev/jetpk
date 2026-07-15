<?php

namespace App\Http\Middleware;

use App\Models\DeveloperUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates /dev/cp protected routes to an active developer_users session (dev_cp_user_id).
 */
class EnsureDeveloperControlPanelAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('ota-developer.enabled')) {
            abort(404);
        }

        $userId = $request->session()->get('dev_cp_user_id');
        if ($userId === null) {
            return redirect()->route('dev.cp.login');
        }

        $developer = DeveloperUser::query()->find($userId);
        if ($developer === null || ! $developer->is_active) {
            $request->session()->forget('dev_cp_user_id');

            return redirect()->route('dev.cp.login');
        }

        if (($developer->must_change_password ?? false)
            && ! $request->routeIs('dev.cp.password', 'dev.cp.password.store', 'dev.cp.logout', 'dev.cp.login', 'dev.cp.login.store')) {
            return redirect()->route('dev.cp.password');
        }

        return $next($request);
    }
}
