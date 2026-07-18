<?php

namespace App\Http\Controllers\Frontend;

use App\Enums\SupportTicketCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Support\StorePublicSupportTicketRequest;
use App\Models\Agency;
use App\Services\Agencies\AboutUsContentPresenter;
use App\Services\Agencies\AgencyBrandingService;
use App\Services\Support\SupportTicketService;
use App\Support\Branding\BrandDisplayResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportController extends Controller
{
    public function __construct(
        protected SupportTicketService $tickets,
        protected AgencyBrandingService $brandingService,
        protected AboutUsContentPresenter $aboutUsPresenter,
    ) {}

    public function support(Request $request): View
    {
        $viewData = [
            'categories' => SupportTicketCategory::cases(),
        ];

        return view(client_view('frontend.support', 'frontend'), $viewData);
    }

    public function store(StorePublicSupportTicketRequest $request): RedirectResponse
    {
        $agency = $this->tickets->resolveDefaultAgency();
        $user = $request->user();
        $validated = $request->validated();

        $requesterName = trim((string) ($validated['name'] ?? ''));
        if ($requesterName === '' && $user !== null) {
            $requesterName = (string) ($user->name ?? 'Customer');
        }

        $booking = $this->tickets->resolveBookingByReference(
            $agency,
            $validated['booking_reference'] ?? null,
        );

        $ticket = $this->tickets->createPublicTicket(
            $agency,
            [
                'subject' => (string) $validated['subject'],
                'category' => (string) $validated['category'],
                'body' => (string) $validated['body'],
                'requester_name' => $requesterName,
                'requester_email' => (string) $validated['email'],
            ],
            $user,
            $booking,
        );

        return redirect()
            ->to(client_route('support.submitted'))
            ->with('support_ticket_reference', $ticket->ticket_reference);
    }

    public function submitted(Request $request): View|RedirectResponse
    {
        $reference = session('support_ticket_reference');
        if (! is_string($reference) || $reference === '') {
            return redirect()->to(client_route('support'));
        }

        $viewData = [
            'ticketReference' => $reference,
            'brandName' => BrandDisplayResolver::displayName(),
        ];

        return view(client_view('frontend.support.submitted', 'frontend'), $viewData);
    }

    public function about(Request $request): View
    {
        $viewData = $this->publicStaticPageBrandData();
        $viewData['aboutUs'] = $this->resolveAboutUsPresentation();

        return view(client_view('frontend.about', 'frontend'), $viewData);
    }

    /**
     * @return array{has_custom: bool, mode: ?string, body_html: string}
     */
    protected function resolveAboutUsPresentation(): array
    {
        $slug = (string) config('ota.default_agency_slug', '');
        if ($slug === '') {
            return $this->aboutUsPresenter->presentForPublic(null);
        }

        $agency = Agency::query()->where('slug', $slug)->first();
        if ($agency === null) {
            return $this->aboutUsPresenter->presentForPublic(null);
        }

        $settings = $this->brandingService->getSettingsForAgency($agency);

        return $this->aboutUsPresenter->presentForPublic($settings);
    }

    /**
     * @return array{brandName: string, footerAbout: string, officeCity: string}
     */
    protected function publicStaticPageBrandData(): array
    {
        $brand = config('ota-brand', []);
        $client = config('ota-client', []);

        $brandName = BrandDisplayResolver::displayName();
        $footerAbout = $client['footer_text'] ?? ($brand['company_note'] ?? '');
        $officeCity = $client['office_city'] ?? '';

        return [
            'brandName' => is_string($brandName) ? $brandName : (string) config('app.name'),
            'footerAbout' => is_string($footerAbout) ? $footerAbout : '',
            'officeCity' => is_string($officeCity) ? $officeCity : '',
        ];
    }
}
