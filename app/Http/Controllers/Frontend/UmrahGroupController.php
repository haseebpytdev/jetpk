<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Frontend\UmrahGroupSearchRequest;
use App\Services\Suppliers\AlHaider\AlHaiderUmrahGroupService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UmrahGroupController extends Controller
{
    public function __construct(
        protected AlHaiderUmrahGroupService $umrahGroups,
    ) {}

    public function index(UmrahGroupSearchRequest $request): View
    {
        $filters = $request->filters();
        $result = $this->umrahGroups->search($filters);
        $airlines = $this->umrahGroups->listAirlinesForFilters();

        $apiState = 'ok';
        if ($result->api_disabled) {
            $apiState = 'disabled';
        } elseif ($result->api_unavailable) {
            $apiState = 'unavailable';
        } elseif ($result->from_stale_cache) {
            $apiState = 'cached';
        } elseif ($result->from_cache) {
            $apiState = 'cached';
        }

        $statusMessage = null;
        if ($apiState === 'disabled') {
            $statusMessage = 'Group ticketing inventory is not available at the moment.';
        } elseif ($apiState === 'unavailable') {
            $statusMessage = 'Group ticketing inventory is temporarily unavailable. Please try again shortly.';
        } elseif ($result->packages === [] && $apiState === 'ok') {
            $statusMessage = 'No group tickets matched your search.';
        }

        return view('frontend.umrah-groups.index', [
            'packages' => $result->packages,
            'filters' => $filters,
            'airlines' => $airlines,
            'warnings' => $result->warnings,
            'apiState' => $apiState,
            'statusMessage' => $statusMessage,
        ]);
    }

    public function show(string $package, Request $request): View
    {
        $detail = $this->umrahGroups->getPackageDetail($package);

        if ($detail === null) {
            abort(404);
        }

        $enquireUrl = route('support', [
            'subject' => 'Group ticketing enquiry — '.$detail->public_id,
        ]);

        return view('frontend.umrah-groups.show', [
            'package' => $detail,
            'enquireUrl' => $enquireUrl,
        ]);
    }
}
