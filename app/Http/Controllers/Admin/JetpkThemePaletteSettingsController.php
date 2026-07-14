<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Services\Branding\JetpkThemePaletteService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * JetPakistan day/night theme palette settings (JetPK deployment only).
 */
class JetpkThemePaletteSettingsController extends Controller
{
    public function __construct(
        private readonly JetpkThemePaletteService $paletteService,
    ) {}

    public function edit(Request $request): View
    {
        $agency = $this->resolveAgency($request);
        Gate::authorize('view', $agency);
        abort_unless($this->paletteService->isJetpkScoped(), 404);

        $palettes = $this->paletteService->palettesForAgency($agency);
        $defaults = $this->paletteService->defaults();

        return view(client_view('settings.theme-palette', 'admin'), [
            'agency' => $agency,
            'palettes' => $palettes,
            'defaults' => $defaults,
            'labels' => config('jetpk-theme-palette.labels', []),
            'helpers' => config('jetpk-theme-palette.helpers', []),
            'keys' => config('jetpk-theme-palette.keys', []),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $agency = $this->resolveAgency($request);
        Gate::authorize('update', $agency);
        abort_unless($this->paletteService->isJetpkScoped(), 404);

        $keys = config('jetpk-theme-palette.keys', []);
        $rules = [];
        foreach (['day', 'night'] as $theme) {
            foreach ($keys as $key) {
                $rules[$theme.'.'.$key] = ['required', 'string', 'max:7'];
            }
        }
        $rules['save_scope'] = ['nullable', 'string', 'in:day,night,both'];

        $validated = $request->validate($rules);

        $day = [];
        $night = [];
        foreach ($keys as $key) {
            $day[$key] = (string) ($validated['day'][$key] ?? '');
            $night[$key] = (string) ($validated['night'][$key] ?? '');
        }

        $this->paletteService->savePalettes(
            $agency,
            $day,
            $night,
            $validated['save_scope'] ?? 'both',
        );

        return back()->with('status', 'theme-palette-updated');
    }

    public function reset(Request $request, string $theme): RedirectResponse
    {
        abort_unless(in_array($theme, ['day', 'night'], true), 404);

        $agency = $this->resolveAgency($request);
        Gate::authorize('update', $agency);
        abort_unless($this->paletteService->isJetpkScoped(), 404);

        $this->paletteService->resetTheme($agency, $theme);

        return back()->with('status', 'theme-palette-reset-'.$theme);
    }

    protected function resolveAgency(Request $request): Agency
    {
        $user = $request->user();
        if ($user->isPlatformAdmin() && $request->filled('agency_id')) {
            return Agency::query()->findOrFail($request->integer('agency_id'));
        }

        return Agency::query()->findOrFail($user->current_agency_id);
    }
}
