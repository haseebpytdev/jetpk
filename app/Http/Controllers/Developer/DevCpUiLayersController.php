<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Developer\UpdateUiLayerSettingsRequest;
use App\Models\DeveloperUser;
use App\Services\Ui\UiLayerSettingsService;
use App\Support\Ui\UiLayerRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Developer CP UI layer override toggles (CSS/JS layers after base assets).
 */
class DevCpUiLayersController extends Controller
{
    public function __construct(
        protected UiLayerSettingsService $settings,
    ) {}

    public function index(): View
    {
        $layers = [];
        foreach (UiLayerRegistry::all() as $layer) {
            $state = $this->settings->effectiveStateFor($layer->key);
            $layers[] = [
                'layer' => $layer,
                'state' => $state,
            ];
        }

        return view('developer.monitoring.ui-layers', [
            'globallyEnabled' => UiLayerRegistry::isGloballyEnabled(),
            'contextLabels' => UiLayerRegistry::contextLabels(),
            'layers' => $layers,
        ]);
    }

    public function update(UpdateUiLayerSettingsRequest $request): RedirectResponse
    {
        /** @var DeveloperUser|null $actor */
        $actor = DeveloperUser::query()->find(session('dev_cp_user_id'));
        if ($actor === null) {
            abort(403);
        }

        $this->settings->applyChanges(
            $request->normalizedChanges(),
            $actor,
            $request,
        );

        return redirect()
            ->route('dev.cp.ui-layers')
            ->with('status', 'UI layer settings saved.');
    }
}
