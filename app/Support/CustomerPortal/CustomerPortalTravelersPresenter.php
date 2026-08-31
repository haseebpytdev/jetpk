<?php

namespace App\Support\CustomerPortal;

use App\Models\SavedTraveler;
use App\Support\Geo\CountryList;
use App\Support\Travel\TravelDocumentFormatter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Customer saved-traveler JSON for Next.js dashboard.
 */
class CustomerPortalTravelersPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function presentIndex(LengthAwarePaginator $travelers, ?SavedTraveler $defaultTraveler = null): array
    {
        return [
            'ok' => true,
            'travelers' => collect($travelers->items())
                ->map(fn (SavedTraveler $traveler) => $this->presentTravelerForList($traveler))
                ->values()
                ->all(),
            'default_traveler' => $defaultTraveler !== null ? $this->presentTravelerForList($defaultTraveler) : null,
            'pagination' => $this->paginationMeta($travelers),
            'countries' => CountryList::forSelect(),
            'create_url' => '/laravel/customer/travelers',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentForm(?SavedTraveler $traveler = null): array
    {
        return [
            'ok' => true,
            'traveler' => $traveler !== null ? $this->presentTravelerForForm($traveler) : $this->emptyTraveler(),
            'countries' => CountryList::forSelect(),
            'submit_url' => $traveler !== null
                ? '/laravel/customer/travelers/'.$traveler->id
                : '/laravel/customer/travelers',
            'method' => $traveler !== null ? 'PATCH' : 'POST',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentStored(SavedTraveler $traveler): array
    {
        return [
            'ok' => true,
            'traveler' => $this->presentTravelerForList($traveler),
            'redirect_url' => '/customer/travelers',
        ];
    }

    /**
     * List/index JSON — masks document numbers; never exposes full document_number.
     *
     * @return array<string, mixed>
     */
    public function presentTravelerForList(SavedTraveler $traveler): array
    {
        return [
            'id' => $traveler->id,
            'title' => $traveler->title,
            'first_name' => $traveler->first_name,
            'last_name' => $traveler->last_name,
            'gender' => $traveler->gender,
            'date_of_birth' => $traveler->date_of_birth?->toDateString(),
            'nationality' => $traveler->nationality,
            'document_type' => $traveler->document_type,
            'document_number_masked' => TravelDocumentFormatter::maskDocumentForList($traveler->document_number),
            'document_expiry' => $traveler->document_expiry?->toDateString(),
            'document_expiry_status' => $traveler->documentExpiryStatus(),
            'completeness_status' => $traveler->completenessStatus(),
            'issuing_country' => $traveler->issuing_country,
            'phone' => $traveler->phone,
            'email' => $traveler->email,
            'is_default' => (bool) $traveler->is_default,
            'edit_url' => '/customer/travelers/'.$traveler->id.'/edit',
            'delete_url' => '/laravel/customer/travelers/'.$traveler->id,
        ];
    }

    /**
     * Checkout list payload for authenticated Customer (masked documents).
     *
     * @param  \Illuminate\Support\Collection<int, SavedTraveler>|iterable<SavedTraveler>  $travelers
     * @return array<string, mixed>
     */
    public function presentCheckoutIndex(iterable $travelers, int|string|null $defaultTravelerId = null): array
    {
        $items = collect($travelers)
            ->map(fn (SavedTraveler $traveler) => $this->presentTravelerForList($traveler))
            ->values()
            ->all();

        return [
            'ok' => true,
            'travelers' => $items,
            'default_traveler_id' => $defaultTravelerId !== null ? (int) $defaultTravelerId : null,
        ];
    }

    /**
     * Checkout fill payload — full document_number for owner only.
     *
     * @return array<string, mixed>
     */
    public function presentCheckoutShow(SavedTraveler $traveler): array
    {
        return [
            'ok' => true,
            'traveler' => array_merge($this->presentTravelerForForm($traveler), [
                'document_expiry_status' => $traveler->documentExpiryStatus(),
                'completeness_status' => $traveler->completenessStatus(),
            ]),
        ];
    }

    /**
     * Authorized create/edit form JSON — full document_number only for replace-with-new-value editing.
     *
     * @return array<string, mixed>
     */
    public function presentTravelerForForm(SavedTraveler $traveler): array
    {
        return [
            'id' => $traveler->id,
            'title' => $traveler->title,
            'first_name' => $traveler->first_name,
            'last_name' => $traveler->last_name,
            'gender' => $traveler->gender,
            'date_of_birth' => $traveler->date_of_birth?->toDateString(),
            'nationality' => $traveler->nationality,
            'document_type' => $traveler->document_type,
            'document_number' => $traveler->document_number,
            'document_number_masked' => TravelDocumentFormatter::maskDocumentForList($traveler->document_number),
            'document_expiry' => $traveler->document_expiry?->toDateString(),
            'issuing_country' => $traveler->issuing_country,
            'phone' => $traveler->phone,
            'email' => $traveler->email,
            'is_default' => (bool) $traveler->is_default,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyTraveler(): array
    {
        return [
            'id' => null,
            'title' => 'Mr',
            'first_name' => '',
            'last_name' => '',
            'gender' => 'male',
            'date_of_birth' => null,
            'nationality' => 'PK',
            'document_type' => 'passport',
            'document_number' => null,
            'document_number_masked' => null,
            'document_expiry' => null,
            'issuing_country' => 'PK',
            'phone' => null,
            'email' => null,
            'is_default' => false,
        ];
    }

    /**
     * @return array<string, int|null>
     */
    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }
}
