<?php

namespace App\Http\Middleware;

use App\Enums\AccountType;
use App\Enums\UserAccountStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountType
{
    public function handle(Request $request, Closure $next, string ...$allowed): Response
    {
        $user = $request->user();

        if ($user === null || $user->account_type === null) {
            abort(403);
        }

        if ($user->status === UserAccountStatus::Suspended || $user->status === UserAccountStatus::Inactive) {
            abort(403, 'Account is not active.');
        }

        $flat = [];
        foreach ($allowed as $chunk) {
            foreach (explode(',', $chunk) as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $flat[] = $part;
                }
            }
        }

        $allowedEnums = [];
        foreach ($flat as $value) {
            $enum = AccountType::tryFrom($value);
            if ($enum !== null) {
                $allowedEnums[] = $enum;
            }
        }

        if ($allowedEnums === [] || ! in_array($user->account_type, $allowedEnums, true)) {
            abort(403);
        }

        return $next($request);
    }
}
