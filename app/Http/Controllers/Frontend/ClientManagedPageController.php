<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\ClientPage;
use App\Services\Client\ClientPageRenderer;
use App\Services\Client\ClientPageSeoResolver;
use App\Support\Client\ClientPageKeys;
use App\Support\Client\ClientManagedPageReservedSlugs;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientManagedPageController extends Controller
{
    public function __construct(
        private readonly ClientPageRenderer $renderer,
        private readonly ClientPageSeoResolver $seoResolver,
    ) {}

    public function faq(): View
    {
        return $this->renderManaged(ClientPageKeys::FAQ, 'themes.frontend.jetpakistan.frontend.faq');
    }

    public function terms(): View
    {
        return $this->renderManaged(ClientPageKeys::TERMS, 'themes.frontend.jetpakistan.frontend.legal.show', 'terms');
    }

    public function privacy(): View
    {
        return $this->renderManaged(ClientPageKeys::PRIVACY, 'themes.frontend.jetpakistan.frontend.legal.show', 'privacy');
    }

    public function customShow(Request $request, string $slug): View
    {
        if (ClientManagedPageReservedSlugs::isReserved($slug)) {
            abort(404);
        }

        $page = ClientPage::query()
            ->where('slug', $slug)
            ->where('enabled', true)
            ->firstOrFail();

        $pageKey = ClientPageKeys::customKey($slug);
        $published = app(\App\Services\Client\ClientPageContentResolver::class)->contentFor($pageKey);
        if ($published === []) {
            abort(404);
        }

        $vm = $this->renderer->viewModel($pageKey);
        $vm['page'] = $page;
        $vm['seo'] = $this->seoResolver->forPage(
            $pageKey,
            (string) ($page->public_title ?? ''),
            '',
            client_url('/'.$slug),
        );

        return view('themes.frontend.jetpakistan.frontend.content-page', $vm);
    }

    private function renderManaged(string $pageKey, string $view, ?string $legalType = null): View
    {
        $vm = $this->renderer->viewModel($pageKey);
        if ($legalType !== null) {
            $vm['legalType'] = $legalType;
        }

        return view($view, $vm);
    }
}
