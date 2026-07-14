<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Support\Ui\MobileViewPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MobileViewController extends Controller
{
    public function __construct(
        protected MobileViewPreference $mobileViewPreference,
    ) {}

    public function mobile(Request $request): RedirectResponse
    {
        return $this->applyPreference($request, MobileViewPreference::MODE_MOBILE);
    }

    public function desktop(Request $request): RedirectResponse
    {
        return $this->applyPreference($request, MobileViewPreference::MODE_DESKTOP);
    }

    public function previewMobile(Request $request): RedirectResponse
    {
        return $this->applyPreference($request, MobileViewPreference::MODE_MOBILE);
    }

    public function previewDesktop(Request $request): RedirectResponse
    {
        return $this->applyPreference($request, MobileViewPreference::MODE_DESKTOP);
    }

    protected function applyPreference(Request $request, string $mode): RedirectResponse
    {
        $this->mobileViewPreference->rememberInSession($request, $mode);

        $redirect = $this->mobileViewPreference->safeRedirectUrl(
            $request->input('redirect'),
            route('home', absolute: false),
        );

        return redirect()
            ->to($redirect)
            ->withCookie($this->mobileViewPreference->makePreferenceCookie($mode));
    }
}
